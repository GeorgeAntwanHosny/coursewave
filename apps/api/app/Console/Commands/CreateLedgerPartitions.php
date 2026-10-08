<?php

namespace App\Console\Commands;

use App\Support\LedgerPartitionManager;
use Illuminate\Console\Command;

class CreateLedgerPartitions extends Command
{
    protected $signature = 'ledger:create-partitions {--months=3 : How many months ahead to ensure partitions exist for}';
    protected $description = 'Create any missing monthly ledger_entries partitions. Run monthly via the scheduler.';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $created = LedgerPartitionManager::createRange(now(), now()->addMonthsNoOverflow($months));

        $this->info('Partitions ensured (IF NOT EXISTS): ' . implode(', ', $created));

        return self::SUCCESS;
    }
}
