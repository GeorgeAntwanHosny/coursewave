<?php

namespace App\Notifications;

use App\Models\Refund;

final class RefundProcessedNotification extends AppNotification
{
    public static function make(Refund $refund): self
    {
        $amount = '$' . number_format($refund->amount_cents / 100, 2);

        return new self(
            'refund_processed',
            'Refund processed',
            "Your refund of {$amount} is on its way back to your original payment method.",
            '/dashboard',
        );
    }
}
