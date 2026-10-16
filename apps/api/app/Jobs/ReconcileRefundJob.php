<?php

namespace App\Jobs;

use App\Actions\Billing\ReconcileRefundAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileRefundJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public int $refundId) {}

    public function handle(ReconcileRefundAction $reconcile): void
    {
        $reconcile->execute($this->refundId);
    }
}
