<?php

namespace App\Notifications;

use App\Models\PayoutBatch;

final class PayoutProcessedNotification extends AppNotification
{
    public static function make(PayoutBatch $batch): self
    {
        $amount = '$' . number_format($batch->amount_cents / 100, 2);

        return new self(
            'payout_processed',
            'Payout processed',
            "{$amount} was sent to your payout account for earnings through {$batch->period_end->format('M j, Y')}.",
            url('/instructor/payout-account'), // Filament lives on the API host, hence absolute
        );
    }
}
