<?php

namespace App\Actions\Billing;

use App\Models\ConsumptionEvent;
use App\Models\RevenueRecognitionSlice;
use App\Models\Subscription;
use App\Support\LargestRemainderSplitter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecognizeSubscriptionRevenueAction
{
    /**
     * Advances every 'pending' slice for $subscription whose date has
     * elapsed (slice_date < $asOfDate) toward 'recognized', per README §3:
     *
     *  - A slice with real consumption that day recognizes using that day's
     *    per-instructor weights, AND sweeps up any earlier still-pending
     *    (zero-consumption) elapsed slices, recognizing them too using THIS
     *    day's weights — "picked up together with the next day that does
     *    have activity."
     *  - A slice with zero consumption that day stays pending and is added
     *    to the backlog, in case a later day catches it up.
     *  - If the term has fully ended (subscription.ends_at has passed) and
     *    backlog still remains, it's resolved using the subscription's
     *    CUMULATIVE consumption share across its whole term. If cumulative
     *    consumption is also zero, the backlog becomes 100% platform
     *    revenue — "no instructor delivered value to attribute it to."
     *
     * Entirely idempotent: every write goes through an INSERT ... ON
     * CONFLICT DO NOTHING keyed on idempotency_key, and each slice is
     * row-locked and re-checked for 'pending' status before being touched,
     * so re-running this for an already-processed date range is a no-op.
     */
    public function execute(Subscription $subscription, Carbon $asOfDate): void
    {
        $elapsedPendingSlices = $subscription->recognitionSlices()
            ->where('status', 'pending')
            ->where('slice_date', '<', $asOfDate->toDateString())
            ->orderBy('slice_date')
            ->get();

        if ($elapsedPendingSlices->isEmpty()) {
            return;
        }

        $backlog = collect();

        foreach ($elapsedPendingSlices as $slice) {
            $weights = $this->consumptionWeightsForDate($subscription, $slice->slice_date);

            if ($weights->isEmpty()) {
                $backlog->push($slice);
                continue;
            }

            $toSweep = $backlog->push($slice);
            $backlog = collect();

            foreach ($toSweep as $sliceToRecognize) {
                $this->recognizeSliceWithWeights($subscription, $sliceToRecognize, $weights);
            }
        }

        if ($backlog->isNotEmpty() && $subscription->ends_at && $subscription->ends_at->lt($asOfDate)) {
            $cumulativeWeights = $this->cumulativeConsumptionWeights($subscription);

            foreach ($backlog as $sliceToRecognize) {
                if ($cumulativeWeights->isNotEmpty()) {
                    $this->recognizeSliceWithWeights($subscription, $sliceToRecognize, $cumulativeWeights);
                } else {
                    $this->recognizeAsFullPlatformRevenue($subscription, $sliceToRecognize);
                }
            }
        }
    }

    protected function consumptionWeightsForDate(Subscription $subscription, Carbon $date): Collection
    {
        return ConsumptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_date', $date->toDateString())
            ->groupBy('instructor_id')
            ->selectRaw('instructor_id, SUM(engagement_seconds) as weight')
            ->pluck('weight', 'instructor_id')
            ->filter(fn ($w) => $w > 0)
            ->map(fn ($w) => (int) $w);
    }

    protected function cumulativeConsumptionWeights(Subscription $subscription): Collection
    {
        return ConsumptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->groupBy('instructor_id')
            ->selectRaw('instructor_id, SUM(engagement_seconds) as weight')
            ->pluck('weight', 'instructor_id')
            ->filter(fn ($w) => $w > 0)
            ->map(fn ($w) => (int) $w);
    }

    protected function recognizeSliceWithWeights(Subscription $subscription, RevenueRecognitionSlice $slice, Collection $weights): void
    {
        DB::transaction(function () use ($subscription, $slice, $weights) {
            $fresh = RevenueRecognitionSlice::whereKey($slice->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== 'pending') {
                return; // already recognized by a concurrent/retried run
            }

            $sliceCents = $fresh->amount_cents;
            $feePercent = (int) config('services.platform.fee_percent');
            $platformAmount = intdiv($sliceCents * $feePercent, 100);
            $instructorPool = $sliceCents - $platformAmount;

            $eventTimestamp = Carbon::parse($fresh->slice_date)->endOfDay();

            $this->writeLedgerEntry(
                instructorId: null,
                entryType: 'platform_fee',
                amountCents: $platformAmount,
                subscriptionId: $subscription->id,
                sliceId: $fresh->id,
                idempotencyKey: "platform_fee:{$subscription->id}:{$fresh->slice_date->toDateString()}",
                eventTimestamp: $eventTimestamp,
            );

            if ($instructorPool > 0) {
                $splits = LargestRemainderSplitter::split($instructorPool, $weights->toArray());
                foreach ($splits as $instructorId => $amountCents) {
                    if ($amountCents <= 0) {
                        continue;
                    }
                    $this->writeLedgerEntry(
                        instructorId: $instructorId,
                        entryType: 'accrual',
                        amountCents: $amountCents,
                        subscriptionId: $subscription->id,
                        sliceId: $fresh->id,
                        idempotencyKey: "accrual:{$subscription->id}:{$fresh->slice_date->toDateString()}:{$instructorId}",
                        eventTimestamp: $eventTimestamp,
                    );
                }
            }

            $fresh->update(['status' => 'recognized', 'recognized_at' => now()]);
        });
    }

    protected function recognizeAsFullPlatformRevenue(Subscription $subscription, RevenueRecognitionSlice $slice): void
    {
        DB::transaction(function () use ($subscription, $slice) {
            $fresh = RevenueRecognitionSlice::whereKey($slice->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== 'pending') {
                return;
            }

            $eventTimestamp = Carbon::parse($fresh->slice_date)->endOfDay();

            // The entire slice, not just the platform fee percentage — no
            // instructor delivered any value this entire term, per README §3.4.
            $this->writeLedgerEntry(
                instructorId: null,
                entryType: 'platform_fee',
                amountCents: $fresh->amount_cents,
                subscriptionId: $subscription->id,
                sliceId: $fresh->id,
                idempotencyKey: "platform_fee:{$subscription->id}:{$fresh->slice_date->toDateString()}",
                eventTimestamp: $eventTimestamp,
            );

            $fresh->update(['status' => 'recognized', 'recognized_at' => now()]);
        });
    }

    protected function writeLedgerEntry(
        ?int $instructorId,
        string $entryType,
        int $amountCents,
        int $subscriptionId,
        int $sliceId,
        string $idempotencyKey,
        Carbon $eventTimestamp,
    ): void {
        $inserted = DB::table('ledger_entries')->insertOrIgnore([[
            'instructor_id' => $instructorId,
            'entry_type' => $entryType,
            'amount_cents' => $amountCents,
            'subscription_id' => $subscriptionId,
            'recognition_slice_id' => $sliceId,
            'idempotency_key' => $idempotencyKey,
            'created_at' => $eventTimestamp,
            'recorded_at' => now(),
        ]]);

        // Only touch the projection if a row was genuinely newly inserted —
        // on a retry, insertOrIgnore returns 0 and the balance must NOT be
        // incremented a second time. This is what keeps instructor_balances
        // correct under "the job ran twice," not a separate dedup check.
        if ($inserted && $instructorId) {
            $this->incrementBalance($instructorId, $amountCents);
        }
    }

    protected function incrementBalance(int $instructorId, int $amountCents): void
    {
        DB::statement(
            'INSERT INTO instructor_balances (instructor_id, owed_cents, paid_cents, updated_at)
             VALUES (?, ?, 0, ?)
             ON CONFLICT (instructor_id) DO UPDATE SET
                owed_cents = instructor_balances.owed_cents + EXCLUDED.owed_cents,
                updated_at = EXCLUDED.updated_at',
            [$instructorId, $amountCents, now()]
        );
    }
}
