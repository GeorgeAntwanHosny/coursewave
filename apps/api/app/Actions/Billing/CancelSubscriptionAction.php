<?php

namespace App\Actions\Billing;

use App\Models\Subscription;

class CancelSubscriptionAction
{
    public function execute(Subscription $subscription): void
    {
        $subscription->recognitionSlices()
            ->where('status', 'pending')
            ->update(['status' => 'voided', 'voided_at' => now()]);

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }
}
