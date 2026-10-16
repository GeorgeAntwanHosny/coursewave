<?php

namespace App\Actions\Billing;

use App\Models\Refund;
use App\Services\Payments\Exceptions\RefundDeclinedException;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

class SubmitRefundAction
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected FinalizeRefundAction $finalize,
        protected RecordRefundAttemptAction $recordAttempt,
    ) {}

    /** pending → submitted → (succeeded | failed | uncertain). Anything not 'pending' is a no-op. */
    public function execute(Refund $refund): void
    {
        $claimed = DB::transaction(function () use ($refund) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending') {
                return null;
            }

            $locked->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'attempts' => $locked->attempts + 1,
            ]);

            return $locked->fresh();
        });

        if ($claimed) {
            $this->sendRefund($claimed);
        }
    }

    /**
     * Calls the provider with the refund's persisted idempotency key — the same key
     * on every attempt. Shared with reconciliation's "not found, resend" path.
     * EVERY call is audit-logged, including a first-time success.
     */
    public function sendRefund(Refund $refund, string $operation = 'refund_initial'): void
    {
        $statusBefore = Refund::whereKey($refund->id)->value('status');
        $startedAt = hrtime(true);

        $result = null;
        $error = null;
        $outcome = 'succeeded';

        try {
            if (! $refund->provider_payment_id) {
                throw new RefundDeclinedException('The subscription has no payment reference to refund against.');
            }

            $result = $this->gateway->createRefund(
                $refund->provider_payment_id,
                $refund->amount_cents,
                $refund->idempotency_key,
            );

            if (in_array($result->status, ['failed', 'canceled'], true)) {
                $outcome = 'declined';
                $error = new RefundDeclinedException("Provider reported the refund as {$result->status}.");
            }
        } catch (RefundDeclinedException $e) {
            $error = $e;
            $outcome = 'declined';
        } catch (\Throwable $e) {
            // Timeout, 5xx, or anything unexpected: we cannot know whether the refund exists.
            $error = $e;
            $outcome = 'uncertain';
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        try {
            match ($outcome) {
                'succeeded' => $this->finalize->markSucceeded($refund->id, $result->refundId),
                'declined' => $this->finalize->markFailed($refund->id, $error->getMessage()),
                default => $this->finalize->markUncertain($refund->id, $error->getMessage()),
            };
        } finally {
            $this->recordAttempt->execute($refund, $operation, $outcome, $statusBefore, $durationMs, $result, $error);
        }
    }
}
