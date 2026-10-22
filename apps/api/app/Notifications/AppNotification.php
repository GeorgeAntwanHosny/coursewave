<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for every in-app notification: stored in the database AND broadcast on the
 * user's private channel (hybrid, so nothing is missed if the recipient is offline).
 *
 * Carries only plain strings. Queued notifications are serialized, and holding Eloquent
 * models would bloat the payload and break if the record is later deleted.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url, // a frontend path ("/certificates") or an absolute URL
    ) {
        // Never send for a transaction that rolled back.
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** Used for both the stored row and the broadcast payload. */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
