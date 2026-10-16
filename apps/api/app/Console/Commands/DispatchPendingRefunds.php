<?php

namespace App\Console\Commands;

use App\Jobs\SubmitRefundJob;
use App\Models\Refund;
use Illuminate\Console\Command;

class DispatchPendingRefunds extends Command
{
    protected $signature = 'refunds:dispatch-pending';
    protected $description = 'Safety net: dispatch a submit job for refunds stuck in pending (e.g. the queue was down). Safe to repeat.';

    public function handle(): int
    {
        $count = 0;

        Refund::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->each(function (Refund $refund) use (&$count) {
                SubmitRefundJob::dispatch($refund->id); // the action locks and re-checks status
                $count++;
            });

        $this->info("Dispatched {$count} pending refund(s).");

        return self::SUCCESS;
    }
}
