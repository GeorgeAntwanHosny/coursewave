<?php

namespace App\Actions\Billing;

use App\Models\Refund;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\RefundResult;
use Illuminate\Support\Facades\DB;

class ReconcileRefundAction
{
    // Providers forget idempotency keys after ~24h. Past this, "not found" no longer
    // proves a resend is safe, so we stop and hand the refund to an admin instead.
    private const RESEND_WINDOW_HOURS = 23;

    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected FinalizeRefundAction $finalize,
        protected SubmitRefundAction $submit,
        protected RecordRefundAttemptAction $recordAttempt,
    ) {}

    /** uncertain|submitted → reconciling → (succeeded | failed | uncertain). */
    public function execute(int $refundId): void
    {
        $refund = DB::transaction(function () use ($refundId) {
            $locked = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['uncertain', 'submitted'], true)) {
                return null;
            }

            $locked->update(['status' => 'reconciling']);

            return $locked->fresh();
        });

        if (! $refund) {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $found = $this->gateway->findRefundByIdempotencyKey(
                (string) $refund->provider_payment_id,
                $refund->idempotency_key,
            );
        } catch (\Throwable $e) {
            try {
                $this->finalize->markUncertain($refund->id, 'Reconciliation lookup failed: ' . $e->getMessage());
            } finally {
                $this->logLookup($refund, 'lookup_failed', $startedAt, null, $e);
            }

            return;
        }

        if ($found) {
            try {
                in_array($found->status, ['failed', 'canceled'], true)
                    ? $this->finalize->markFailed($refund->id, "Provider reported the refund as {$found->status}.")
                    : $this->finalize->markSucceeded($refund->id, $found->refundId);
            } finally {
                $this->logLookup($refund, 'found', $startedAt, $found);
            }

            return;
        }

        $this->logLookup($refund, 'not_found', $startedAt);

        if ($refund->submitted_at && $refund->submitted_at->lt(now()->subHours(self::RESEND_WINDOW_HOURS))) {
            $this->finalize->markUncertain(
                $refund->id,
                'Not found at the provider, but the idempotency window has expired — verify in the provider dashboard before resending.',
                scheduleReconcile: false,
            );

            return;
        }

        // No refund exists at the provider and the key is still honored. Resend with the
        // SAME key: if the original did land after all, the provider returns it.
        $this->submit->sendRefund($refund, 'refund_resend');
    }

    protected function logLookup(Refund $refund, string $outcome, int|float $startedAt, ?RefundResult $result = null, ?\Throwable $error = null): void
    {
        $this->recordAttempt->execute(
            $refund,
            'lookup',
            $outcome,
            'reconciling',
            (int) ((hrtime(true) - $startedAt) / 1_000_000),
            $result,
            $error,
        );
    }
}
