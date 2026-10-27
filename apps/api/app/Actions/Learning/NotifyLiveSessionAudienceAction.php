<?php

namespace App\Actions\Learning;

use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\LiveSessionNotification;
use Illuminate\Support\Facades\Notification;

class NotifyLiveSessionAudienceAction
{
    /**
     * "Enrolled" here means: a student with an active subscription who has started at least
     * one lesson of the session's course. (There is no enrollment table — one subscription
     * unlocks everything — so progress is the signal that someone is actually taking the course.)
     * Returns how many students were addressed.
     *
     * @param  'scheduled'|'rescheduled'|'canceled'|'reminder'  $event
     */
    public function execute(LiveSession $session, string $event): int
    {
        $session->loadMissing('course.instructor');

        $notification = match ($event) {
            'scheduled' => LiveSessionNotification::scheduled($session),
            'rescheduled' => LiveSessionNotification::rescheduled($session),
            'canceled' => LiveSessionNotification::canceled($session),
            'reminder' => LiveSessionNotification::reminder($session),
            default => throw new \InvalidArgumentException("Unknown live session event [{$event}]."),
        };

        if ($session->course?->status !== 'published') {
            return 0; // an unpublished course has no students to tell
        }

        $count = 0;

        User::query()
            ->role('student')
            ->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))
            ->whereHas('lessonProgress', fn ($q) => $q->where('course_id', $session->course_id))
            ->chunkById(200, function ($students) use ($notification, &$count) {
                try {
                    Notification::send($students, $notification);
                } catch (\Throwable $e) {
                    report($e); // one bad chunk must not stop the rest of the audience
                }

                $count += $students->count();
            });

        return $count;
    }
}
