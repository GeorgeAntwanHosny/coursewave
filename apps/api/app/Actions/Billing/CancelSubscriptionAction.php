<?php

namespace App\Actions\Billing;

use App\Jobs\SubmitRefundJob;
use App\Models\Refund;
use App\Models\RevenueRecognitionSlice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CancelSubscriptionAction
{
    /** What cancelling right now would refund: pending slices that have not yet elapsed. */
    public static function refundableCents(Subscription $subscription): int
    {
        return (int) $subscription->recognitionSlices()
            ->where('status', 'pending')
            ->where('slice_date', '>=', now()->toDateString())
            ->sum('amount_cents');
    }

    /**
     * Cancels an active subscription and, when unused days remain, creates the
     * refund row for them — all in one transaction.
     *
     *  - Voids only 'pending' slices with slice_date >= today.
     *    A recognized slice, or an elapsed one waiting for the daily job, is never
     *    voided or refunded, and no instructor is clawed back.
     *  - The refund amount is the sum of exactly the slices voided here, summed
     *    from the same locked rows that are updated, so it cannot drift from them.
     *  - ends_at is truncated to today, so the term-end backlog sweep for any
     *    elapsed leftovers fires on the next recognition run instead of waiting
     *    for the original end date.
     *  - Idempotent: cancelling an already-cancelled subscription returns the
     *    existing refund (or null) and changes nothing.
     *
     * The provider call itself is a separate job dispatched AFTER commit — a
     * refund is never sent for a cancellation that rolled back.
     */
    public function execute(Subscription $subscription, string $reason = 'plan_switch', ?int $requestedById = null): ?Refund
    {
        $refund = DB::transaction(function () use ($subscription, $reason, $requestedById) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                return $locked->refund;
            }

            $today = now()->toDateString();

            $slices = RevenueRecognitionSlice::where('subscription_id', $locked->id)
                ->where('status', 'pending')
                ->where('slice_date', '>=', $today)
                ->lockForUpdate()
                ->get();

            $refundCents = (int) $slices->sum('amount_cents');

            if ($slices->isNotEmpty()) {
                RevenueRecognitionSlice::whereIn('id', $slices->pluck('id'))
                    ->update(['status' => 'voided', 'voided_at' => now()]);
            }

            $endsAt = $locked->ends_at && $locked->ends_at->toDateString() < $today
                ? $locked->ends_at->toDateString()
                : $today;

            $locked->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'ends_at' => $endsAt,
            ]);

            if ($refundCents <= 0) {
                return null; // nothing unused — every remaining day already elapsed
            }

            return Refund::create([
                'subscription_id' => $locked->id,
                'student_id' => $locked->student_id,
                'requested_by_id' => $requestedById,
                'provider' => $locked->provider,
                'provider_payment_id' => $locked->provider_payment_id,
                'amount_cents' => $refundCents,
                'reason' => $reason,
                'status' => 'pending',
                'idempotency_key' => "refund:{$locked->id}", // derived from the subscription id, generated once
            ]);
        });

        if ($refund && $refund->status === 'pending') {
            // afterCommit: this may run inside ConfirmSubscriptionAction's transaction.
            SubmitRefundJob::dispatch($refund->id)->afterCommit();
        }

        return $refund;
    }
}
