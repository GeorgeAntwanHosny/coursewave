<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for every in-app notification: stored in the database AND broadcast on the
 * user's private channel (hybrid, so nothing is missed if the recipient is offline).
 *
 * Carries only plain strings. Queued notifications are serialized, and holding Eloquent
 * models would bloat the payload and break if the record is later deleted.
 *
 * One stored row serves two bells: our flat fields (kind/title/body/url) for the Next.js
 * bell, and Filament's own format so the instructor and admin panels list it too.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,          // a frontend path ("/certificates") or an absolute URL
        public readonly ?string $icon = null, // a heroicon name, shown in the Filament bell
        public readonly ?string $status = null, // success | warning | danger | info
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
        // Filament's bell only lists rows whose data->format is "filament", so the row carries
        // that format plus our own flat fields. title and body are the same values in both.
        return array_merge($this->filamentPayload(), [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ]);
    }

    protected function filamentPayload(): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body);

        match ($this->status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            'info' => $notification->info(),
            default => null,
        };

        if ($this->icon) {
            $notification->icon($this->icon); // after the status, so it overrides the status's default icon
        }

        if ($this->url !== '') {
            $notification->actions([$this->viewAction()]);
        }

        return $notification->getDatabaseMessage();
    }

    protected function viewAction(): object
    {
        // Filament 3 has its own notification action class; 4 and later use the shared Actions one.
        $class = $this->filamentMajorVersion() >= 4
            ? \Filament\Actions\Action::class
            : \Filament\Notifications\Actions\Action::class;

        return $class::make('view')->label('View')->url($this->absoluteUrl());
    }

    protected function filamentMajorVersion(): int
    {
        try {
            $version = \Composer\InstalledVersions::getPrettyVersion('filament/filament');
        } catch (\Throwable) {
            $version = null;
        }

        return (int) ltrim((string) ($version ?? '3'), 'v');
    }

    /** Frontend paths are relative to the Next.js app, which is not the host Filament runs on. */
    protected function absoluteUrl(): string
    {
        if (preg_match('#^https?://#i', $this->url)) {
            return $this->url;
        }

        return rtrim((string) config('app.frontend_url'), '/') . '/' . ltrim($this->url, '/');
    }

    protected static function usd(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
