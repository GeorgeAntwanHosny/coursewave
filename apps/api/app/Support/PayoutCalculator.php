<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayoutCalculator
{
    /**
     * Outstanding = accruals up to the end of $periodEnd, plus every payout and
     * refund_adjustment entry.
     * Computed from ledger_entries only — never from the instructor_balances
     * projection. Money inside an in-flight batch is not yet ledgered, which is
     * why only one in-flight batch per instructor is allowed (partial unique index).
     */
    public static function outstandingCents(int $instructorId, Carbon $periodEnd): int
    {
        $accrued = (int) DB::table('ledger_entries')
            ->where('instructor_id', $instructorId)
            ->where('entry_type', 'accrual')
            ->where('created_at', '<=', $periodEnd->copy()->endOfDay())
            ->sum('amount_cents');

        $debits = (int) DB::table('ledger_entries')
            ->where('instructor_id', $instructorId)
            ->whereIn('entry_type', ['payout', 'refund_adjustment'])
            ->sum('amount_cents');

        return $accrued + $debits;
    }
}
