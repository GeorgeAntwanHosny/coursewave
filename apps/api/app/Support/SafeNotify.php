<?php

namespace App\Support;

use Illuminate\Notifications\Notification;

class SafeNotify
{
    /**
     * Sends a notification without ever letting a failure reach the caller. A broken
     * queue, a missing table or an unreachable broadcaster must not roll back a payout,
     * fail a refund, or lose a student's lesson progress. The failure is reported instead.
     */
    public static function send(object $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
