<?php

namespace App\Jobs;

use App\Actions\Billing\RecognizeSubscriptionRevenueAction;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecognizeRevenueChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public array $subscriptionIds,
        public string $asOfDate,
    ) {}

    public function handle(RecognizeSubscriptionRevenueAction $action): void
    {
        $asOfDate = Carbon::parse($this->asOfDate);

        Subscription::whereIn('id', $this->subscriptionIds)->each(function (Subscription $subscription) use ($action, $asOfDate) {
            $action->execute($subscription, $asOfDate);
        });
    }
}
