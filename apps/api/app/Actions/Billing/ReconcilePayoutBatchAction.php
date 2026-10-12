<?php

namespace App\Actions\Billing;

use App\Models\PayoutBatch;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\TransferResult;
use Illuminate\Support\Facades\DB;

class ReconcilePayoutBatchAction
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected FinalizePayoutBatchAction $finalize,
        protected SubmitPayoutBatchAction $submit,
        protected RecordPayoutAttemptAction $recordAttempt,
    ) {}

    /** uncertain|submitted → reconciling → (succeeded | failed | uncertain). */
    public function execute(int $batchId): void
    {
        $batch = DB::transaction(function () use ($batchId) {
            $locked = PayoutBatch::whereKey($batchId)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['uncertain', 'submitted'], true)) {
                return null;
            }

            $locked->update(['status' => 'reconciling']);

            return $locked->fresh();
        });

        if (! $batch) {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $found = $this->gateway->findTransferByIdempotencyKey($batch->gateway_idempotency_key);
        } catch (\Throwable $e) {
            try {
                $this->finalize->markUncertain($batch->id, 'Reconciliation lookup failed: ' . $e->getMessage());
            } finally {
                $this->logLookup($batch, 'lookup_failed', $startedAt, null, $e);
            }

            return;
        }

        if ($found) {
            try {
                $this->finalize->markSucceeded($batch->id, $found->transferId);
            } finally {
                $this->logLookup($batch, 'found', $startedAt, $found);
            }

            return;
        }

        // No transfer exists at the provider. Log that finding, then resend with
        // the SAME key: if the original did land after all, the provider returns
        // it rather than creating a second one.
        $this->logLookup($batch, 'not_found', $startedAt);
        $this->submit->sendTransfer($batch, 'transfer_resend');
    }

    protected function logLookup(PayoutBatch $batch, string $outcome, int|float $startedAt, ?TransferResult $result = null, ?\Throwable $error = null): void
    {
        $this->recordAttempt->execute(
            $batch,
            'lookup',
            $outcome,
            'reconciling',
            (int) ((hrtime(true) - $startedAt) / 1_000_000),
            $result,
            $error,
        );
    }
}
