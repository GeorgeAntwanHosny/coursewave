<?php

namespace App\Actions\Billing;

use App\Models\RevenueRecognitionSlice;
use App\Models\Subscription;

class GenerateRecognitionSlicesAction
{
    /**
     * Splits total_amount_cents into term_days slices, integer division,
     * any leftover cents placed on the final slice.
     * Uses firstOrCreate keyed on the (subscription_id, slice_date) unique
     * constraint so re-running this for an already-sliced subscription is a no-op.
     */
    public function execute(Subscription $subscription): void
    {
        $termDays = $subscription->term_days;
        $total = $subscription->total_amount_cents;

        $baseAmount = intdiv($total, $termDays);
        $remainder = $total - ($baseAmount * $termDays);

        for ($i = 0; $i < $termDays; $i++) {
            $amount = $baseAmount + ($i === $termDays - 1 ? $remainder : 0);

            RevenueRecognitionSlice::firstOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'slice_date' => $subscription->starts_at->copy()->addDays($i)->toDateString(),
                ],
                [
                    'amount_cents' => $amount,
                    'status' => 'pending',
                ]
            );
        }
    }
}
