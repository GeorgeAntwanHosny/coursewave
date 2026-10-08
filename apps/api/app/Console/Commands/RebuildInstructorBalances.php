<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildInstructorBalances extends Command
{
    protected $signature = 'ledger:rebuild-balances';
    protected $description = 'Rebuilds instructor_balances from ledger_entries alone — the projection is never authoritative, per README §5.';

    public function handle(): int
    {
        DB::transaction(function () {
            DB::table('instructor_balances')->truncate();

            DB::statement(<<<SQL
                INSERT INTO instructor_balances (instructor_id, owed_cents, paid_cents, updated_at)
                SELECT
                    instructor_id,
                    COALESCE(SUM(amount_cents) FILTER (WHERE entry_type = 'accrual'), 0) AS owed_cents,
                    COALESCE(SUM(amount_cents) FILTER (WHERE entry_type = 'payout'), 0) AS paid_cents,
                    now()
                FROM ledger_entries
                WHERE instructor_id IS NOT NULL
                GROUP BY instructor_id
            SQL);
        });

        $this->info('instructor_balances rebuilt from ledger_entries.');

        return self::SUCCESS;
    }
}
