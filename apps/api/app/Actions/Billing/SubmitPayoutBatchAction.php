<?php

namespace App\Actions\Billing;

use App\Models\PayoutBatch;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

class SubmitPayoutBatchAction
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected FinalizePayoutBatchAction $finalize,
        protected RecordPayoutAttemptAction $recordAttempt,
    ) {}

    /** pending → submitted → (succeeded | failed | uncertain). Anything not 'pending' is a no-op. */
    public function execute(PayoutBatch $batch): void
    {
        $claimed = DB::transaction(function () use ($batch) {
            $locked = PayoutBatch::whereKey($batch->id)->lockForUpdate()->first();

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
            $this->sendTransfer($claimed);
        }
    }

    /**
     * Calls the provider with the batch's persisted idempotency key — the same
     * key on every attempt, so a retry can never create a second transfer.
     * Shared with reconciliation's "not found, resend" path. EVERY call is
     * audit-logged, including a first-time success.
     */
    public function sendTransfer(PayoutBatch $batch, string $operation = 'transfer_initial'): void
    {
        $statusBefore = PayoutBatch::whereKey($batch->id)->value('status');
        $startedAt = hrtime(true);

        $result = null;
        $error = null;
        $outcome = 'succeeded';

        try {
            $result = $this->gateway->createTransfer(
                $batch->provider_account_id,
                $batch->amount_cents,
                $batch->gateway_idempotency_key,
            );
        } catch (PayoutDeclinedException $e) {
            $error = $e;
            $outcome = 'declined';
        } catch (\Throwable $e) {
            // Timeout, 5xx, or anything unexpected: we cannot know whether money moved.
            $error = $e;
            $outcome = 'uncertain';
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        try {
            match ($outcome) {
                'succeeded' => $this->finalize->markSucceeded($batch->id, $result->transferId),
                'declined' => $this->finalize->markFailed($batch->id, $error->getMessage()),
                default => $this->finalize->markUncertain($batch->id, $error->getMessage()),
            };
        } finally {
            $this->recordAttempt->execute($batch, $operation, $outcome, $statusBefore, $durationMs, $result, $error);
        }
    }
}
