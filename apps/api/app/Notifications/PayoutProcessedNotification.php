<?php

namespace App\Notifications;

use App\Filament\Instructor\Pages\PayoutAccount;
use App\Models\PayoutBatch;

final class PayoutProcessedNotification extends AppNotification
{
    public static function make(PayoutBatch $batch): self
    {
        return new self(
            'payout_processed',
            'Payout processed',
            self::usd($batch->amount_cents) . " was sent to your payout account for earnings through {$batch->period_end->format('M j, Y')}.",
            PayoutAccount::getUrl(panel: 'instructor'),
            'heroicon-o-banknotes',
            'success',
        );
    }
}
