<?php

namespace App\Jobs;

use App\Actions\Billing\ReconcileRefundAction;
use App\Actions\Billing\SubmitRefundAction;
use App\Models\Refund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SubmitRefundJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public int $refundId) {}

    public function handle(SubmitRefundAction $submit, ReconcileRefundAction $reconcile): void
    {
        $refund = Refund::find($this->refundId);

        if (! $refund) {
            return;
        }

        match ($refund->status) {
            'pending' => $submit->execute($refund),
            // Crash recovery: a previous attempt moved it to 'submitted' but died before
            // recording the outcome. We can't know what happened → reconcile.
            'submitted' => $reconcile->execute($refund->id),
            default => null, // already resolved; a retried job is a no-op
        };
    }
}
