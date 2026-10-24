<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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

    /** Sends the notification to every user with the admin role, with the same never-throws guarantee. */
    public static function toAdmins(Notification $notification): void
    {
        try {
            $admins = User::role('admin')->get();

            if ($admins->isNotEmpty()) {
                NotificationFacade::send($admins, $notification);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
