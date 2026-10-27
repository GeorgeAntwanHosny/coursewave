<?php

namespace App\Notifications;

use App\Models\LiveSession;

/** Everything students hear about live sessions. One factory per event; every link opens the student's Live page. */
final class LiveSessionNotification extends AppNotification
{
    public static function scheduled(LiveSession $session): self
    {
        $session->loadMissing('course.instructor');

        return new self(
            'live_session_scheduled',
            "New live session: {$session->title}",
            "{$session->course->title} with {$session->course->instructor->name} · " . self::when($session) . '. The join link opens a few minutes before it starts.',
            '/live',
            'heroicon-o-video-camera',
            'info',
        );
    }

    public static function rescheduled(LiveSession $session): self
    {
        $session->loadMissing('course');

        return new self(
            'live_session_rescheduled',
            "Live session rescheduled: {$session->title}",
            "{$session->course->title} is now " . self::when($session) . '.',
            '/live',
            'heroicon-o-calendar-days',
            'warning',
        );
    }

    public static function canceled(LiveSession $session): self
    {
        $session->loadMissing('course');

        return new self(
            'live_session_canceled',
            "Live session canceled: {$session->title}",
            "{$session->course->title} on " . self::when($session) . ' was canceled.',
            '/live',
            'heroicon-o-x-circle',
            'warning',
        );
    }

    public static function reminder(LiveSession $session): self
    {
        $session->loadMissing('course');
        $minutes = max(1, (int) ceil(now()->diffInSeconds($session->starts_at, false) / 60));

        return new self(
            'live_session_reminder',
            "Starting soon: {$session->title}",
            "Starts in about {$minutes} minute(s) (" . self::when($session) . '). Open Live sessions to join.',
            '/live',
            'heroicon-o-clock',
            'info',
        );
    }

    /** Stored as plain text, so the app time zone is spelled out. The Next.js pages show each viewer's own zone. */
    protected static function when(LiveSession $session): string
    {
        return $session->starts_at->copy()->timezone(config('app.timezone'))->format('D, M j \a\t H:i T');
    }
}
