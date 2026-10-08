<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerPartitionManager
{
    /**
     * Creates monthly partitions of ledger_entries covering [$start, $end]
     * (inclusive by month), idempotently (IF NOT EXISTS). Used both by the
     * initial migration and by the recurring `ledger:create-partitions`
     * command — "partitioned by month" requirement.
     */
    public static function createRange(Carbon $start, Carbon $end): array
    {
        $created = [];
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $partitionName = 'ledger_entries_' . $cursor->format('Y_m');
            $rangeStart = $cursor->toDateString();
            $rangeEnd = $cursor->copy()->addMonthNoOverflow()->toDateString();

            DB::statement(
                "CREATE TABLE IF NOT EXISTS {$partitionName} PARTITION OF ledger_entries "
                . "FOR VALUES FROM ('{$rangeStart}') TO ('{$rangeEnd}')"
            );

            $created[] = $partitionName;
            $cursor->addMonthNoOverflow();
        }

        return $created;
    }
}
