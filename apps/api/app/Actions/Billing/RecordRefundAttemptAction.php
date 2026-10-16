<?php

namespace App\Actions\Billing;

use App\Models\Refund;
use App\Models\RefundAttempt;
use App\Services\Payments\Exceptions\RefundProviderException;
use App\Services\Payments\RefundResult;
use Illuminate\Support\Facades\DB;

class RecordRefundAttemptAction
{
    /**
     * Appends one audit row for a provider interaction. Called AFTER
     * the state change it describes and never inside the money-moving transaction.
     * An audit failure is reported but must not alter the refund flow.
     */
    public function execute(
        Refund $refund,
        string $operation,
        string $outcome,
        ?string $statusBefore,
        int $durationMs,
        ?RefundResult $result = null,
        ?\Throwable $error = null,
    ): void {
        try {
            DB::transaction(function () use ($refund, $operation, $outcome, $statusBefore, $durationMs, $result, $error) {
                Refund::whereKey($refund->id)->lockForUpdate()->first();

                $statusAfter = Refund::whereKey($refund->id)->value('status');
                $next = (int) RefundAttempt::where('refund_id', $refund->id)->max('attempt_number') + 1;
                $providerError = $error instanceof RefundProviderException ? $error : null;

                RefundAttempt::create([
                    'refund_id' => $refund->id,
                    'attempt_number' => $next,
                    'operation' => $operation,
                    'outcome' => $outcome,
                    'status_before' => $statusBefore,
                    'status_after' => $statusAfter,
                    'provider' => $refund->provider,
                    'idempotency_key' => $refund->idempotency_key,
                    'amount_cents' => $refund->amount_cents,
                    'provider_refund_id' => $result?->refundId,
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
