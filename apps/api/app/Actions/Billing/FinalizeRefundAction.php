<?php

namespace App\Actions\Billing;

use App\Jobs\ReconcileRefundJob;
use App\Models\Refund;
use App\Notifications\AdminBillingNotification;
use App\Notifications\RefundProcessedNotification;
use App\Support\SafeNotify;
use Illuminate\Support\Facades\DB;

class FinalizeRefundAction
{
    private const UNRESOLVED = ['submitted', 'uncertain', 'reconciling'];

    /**
     * Records a confirmed refund. Deliberately writes NO ledger entry: the refunded
     * days were voided before they were ever recognized, so no instructor accrual or
     * platform fee exists. Safe to call twice; the student and the
     * admins are notified once, after the transaction has committed.
     */
    public function markSucceeded(int $refundId, string $providerRefundId): void
    {
        $justSucceeded = DB::transaction(function () use ($refundId, $providerRefundId) {
            $refund = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $refund || ! in_array($refund->status, self::UNRESOLVED, true)) {
                return false;
            }

            $refund->update([
                'status' => 'succeeded',
                'provider_refund_id' => $providerRefundId,
                'failure_reason' => null,
                'completed_at' => now(),
            ]);

            return true;
        });

        if ($justSucceeded) {
            $refund = Refund::with('student')->find($refundId);

            if ($refund?->student) {
                SafeNotify::send($refund->student, RefundProcessedNotification::make($refund));
                SafeNotify::toAdmins(AdminBillingNotification::refundProcessed($refund));
            }
        }
    }

    /** Provider explicitly declined. Terminal: an admin resolves it in the provider dashboard. */
    public function markFailed(int $refundId, string $reason): void
    {
        $justFailed = DB::transaction(function () use ($refundId, $reason) {
            $refund = Refund::whereKey($refundId)->lockForUpdate()->first();

            if (! $refund || ! in_array($refund->status, self::UNRESOLVED, true)) {
                return false;
            }

            $refund->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($reason, 0, 500),
                'completed_at' => now(),
            ]);

            return true;
        });

        if ($justFailed) {
            $refund = Refund::with('student')->find($refundId);

            if ($refund?->student) {
                // Admins only: the student isn't told a refund failed until a human has resolved it.
                SafeNotify::toAdmins(AdminBillingNotification::refundFailed($refund, mb_substr($reason, 0, 200)));
            }
        }
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

        if ($count === null) {
            return;
        }

        if ($scheduleReconcile && $count <= (int) config('services.refunds.max_reconcile_attempts')) {
            ReconcileRefundJob::dispatch($refundId)->delay(now()->addMinutes(min(2 ** $count, 60)));

            return;
        }

        // Out of automatic attempts (or the idempotency window expired): a human has to look.
        $refund = Refund::with('student')->find($refundId);

        if ($refund?->student) {
            SafeNotify::toAdmins(AdminBillingNotification::refundStuck($refund));
        }
    }
}
