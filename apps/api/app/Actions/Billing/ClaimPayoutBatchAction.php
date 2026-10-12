<?php

namespace App\Actions\Billing;

use App\Models\InstructorPayoutAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Support\PayoutCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimPayoutBatchAction
{
    /**
     * Claims by INSERT: the unique (instructor, period) constraint
     * and the one-in-flight-per-instructor partial index mean that of N racing
     * callers exactly one insert wins; the rest get null. The amount is then
     * computed ONCE from the ledger and stored on the batch.
     *
     * Returns the batch in 'pending' state, or null if nothing should be paid.
     */
    public function execute(User $instructor, Carbon $periodEnd): ?PayoutBatch
    {
        $account = InstructorPayoutAccount::where('instructor_id', $instructor->id)
            ->where('payouts_enabled', true)
            ->first();

        if (! $account) {
            return null;
        }

        $lastEnd = PayoutBatch::where('instructor_id', $instructor->id)->max('period_end');
        $firstAccrual = DB::table('ledger_entries')
            ->where('instructor_id', $instructor->id)
            ->where('entry_type', 'accrual')
            ->min('created_at');

        $periodStart = $lastEnd
            ? Carbon::parse($lastEnd)->addDay()->startOfDay()
            : ($firstAccrual ? Carbon::parse($firstAccrual)->startOfDay() : $periodEnd->copy()->startOfDay());

        if ($periodStart->gt($periodEnd)) {
            return null; // this period (or a later one) was already claimed
        }

        $inserted = DB::table('payout_batches')->insertOrIgnore([[
            'instructor_id' => $instructor->id,
            'provider' => $account->provider,
            'provider_account_id' => $account->provider_account_id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount_cents' => 0,
            'status' => 'claimed',
            'gateway_idempotency_key' => (string) Str::uuid(), // generated once, here, never again
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if (! $inserted) {
            return null; // another run/server already holds the claim
        }

        $batch = PayoutBatch::where('instructor_id', $instructor->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->firstOrFail();

        return DB::transaction(function () use ($batch, $instructor, $periodEnd) {
            $locked = PayoutBatch::whereKey($batch->id)->lockForUpdate()->first();
            $amount = PayoutCalculator::outstandingCents($instructor->id, $periodEnd);

            if ($amount < (int) config('services.payouts.minimum_cents')) {
                $locked->update(['status' => 'skipped', 'amount_cents' => max(0, $amount)]);

                return null;
            }

            $locked->update(['status' => 'pending', 'amount_cents' => $amount]);

            return $locked->fresh();
        });
    }
}
