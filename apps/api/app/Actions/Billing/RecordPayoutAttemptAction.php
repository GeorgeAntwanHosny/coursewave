<?php

namespace App\Actions\Billing;

use App\Models\PayoutBatch;
use App\Models\PayoutBatchAttempt;
use App\Services\Payments\Exceptions\PayoutProviderException;
use App\Services\Payments\TransferResult;
use Illuminate\Support\Facades\DB;

class RecordPayoutAttemptAction
{
    /**
     * Appends one audit row for a provider interaction. Called AFTER the state
     * change it describes (so status_after is the batch's real status) and never
     * inside the money-moving transaction. An audit failure is reported but must
     * not alter the payout flow: the batch state machine stays authoritative.
     */
    public function execute(
        PayoutBatch $batch,
        string $operation,
        string $outcome,
        ?string $statusBefore,
        int $durationMs,
        ?TransferResult $result = null,
        ?\Throwable $error = null,
    ): void {
        try {
            DB::transaction(function () use ($batch, $operation, $outcome, $statusBefore, $durationMs, $result, $error) {
                // Lock the batch row so concurrent writers get distinct attempt numbers.
                PayoutBatch::whereKey($batch->id)->lockForUpdate()->first();

                $statusAfter = PayoutBatch::whereKey($batch->id)->value('status');
                $next = (int) PayoutBatchAttempt::where('payout_batch_id', $batch->id)->max('attempt_number') + 1;

                $providerError = $error instanceof PayoutProviderException ? $error : null;

                PayoutBatchAttempt::create([
                    'payout_batch_id' => $batch->id,
                    'attempt_number' => $next,
                    'operation' => $operation,
                    'outcome' => $outcome,
                    'status_before' => $statusBefore,
                    'status_after' => $statusAfter,
                    'provider' => $batch->provider,
                    'idempotency_key' => $batch->gateway_idempotency_key,
                    'amount_cents' => $batch->amount_cents,
                    'provider_transfer_id' => $result?->transferId,
                    'http_status' => $providerError?->httpStatus,
                    'provider_request_id' => $result?->requestId ?? $providerError?->providerRequestId,
                    'error_code' => $providerError?->errorCode,
                    'error_message' => $error ? mb_substr($error->getMessage(), 0, 2000) : null,
                    'response' => $result?->response
                        ?? $providerError?->response
                        ?? ($error ? ['exception' => $error::class] : null),
                    'duration_ms' => $durationMs,
                ]);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
