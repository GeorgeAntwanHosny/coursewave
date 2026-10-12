<?php

namespace App\Jobs;

use App\Actions\Billing\ReconcilePayoutBatchAction;
use App\Actions\Billing\SubmitPayoutBatchAction;
use App\Models\PayoutBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SubmitPayoutBatchJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(SubmitPayoutBatchAction $submit, ReconcilePayoutBatchAction $reconcile): void
    {
        $batch = PayoutBatch::find($this->batchId);

        if (! $batch) {
            return;
        }

        match ($batch->status) {
            'pending' => $submit->execute($batch),
            // Crash recovery: a previous attempt moved it to 'submitted' but died
            // before recording the outcome. We can't know what happened → reconcile.
            'submitted' => $reconcile->execute($batch->id),
            default => null, // already resolved; a retried job is a no-op
        };
    }
}
