<?php

namespace App\Actions\Billing;

use App\Jobs\ReconcileRefundJob;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class FinalizeRefundAction
{
    private const UNRESOLVED = ['submitted', 'uncertain', 'reconciling'];

    /**
     * Records a confirmed refund. Deliberately writes NO ledger entry: the refunded
     * days were voided before they were ever recognized, so no instructor accrual or
     * platform fee exists for them (README §7). Safe to call twice.
     */
    public function markSucceeded(int $refundId, string $providerRefundId): void
    {
        DB::transaction(function () use ($refundId, $providerRefundId) {
            $refund = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $refund || ! in_array($refund->status, self::UNRESOLVED, true)) {
                return;
            }

            $refund->update([
                'status' => 'succeeded',
                'provider_refund_id' => $providerRefundId,
                'failure_reason' => null,
                'completed_at' => now(),
            ]);

        });
    }

    /** Provider explicitly declined. Terminal: an admin resolves it in the provider dashboard. */
    public function markFailed(int $refundId, string $reason): void
    {
        DB::transaction(function () use ($refundId, $reason) {
            $refund = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $refund || ! in_array($refund->status, self::UNRESOLVED, true)) {
                return;
            }

            $refund->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($reason, 0, 500),
                'completed_at' => now(),
            ]);
        });
    }

    /** Outcome unknown. Never failed, never succeeded — optionally schedule reconciliation with backoff. */
    public function markUncertain(int $refundId, string $reason, bool $scheduleReconcile = true): void
    {
        $count = DB::transaction(function () use ($refundId, $reason) {
            $refund = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $refund || ! in_array($refund->status, self::UNRESOLVED, true)) {
                return null;
            }

            $refund->update([
                'status' => 'uncertain',
                'failure_reason' => mb_substr($reason, 0, 500),
                'uncertain_count' => $refund->uncertain_count + 1,
            ]);

            return $refund->uncertain_count;
        });

        if ($scheduleReconcile && $count !== null && $count <= (int) config('services.refunds.max_reconcile_attempts')) {
            ReconcileRefundJob::dispatch($refundId)->delay(now()->addMinutes(min(2 ** $count, 60)));
        }
        // Past the max it stays 'uncertain' for an admin — it is never auto-failed.
    }
}
