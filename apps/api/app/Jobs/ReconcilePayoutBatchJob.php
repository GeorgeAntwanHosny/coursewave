<?php

namespace App\Jobs;

use App\Actions\Billing\ReconcilePayoutBatchAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ReconcilePayoutBatchJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(ReconcilePayoutBatchAction $reconcile): void
    {
        $reconcile->execute($this->batchId);
    }
}
