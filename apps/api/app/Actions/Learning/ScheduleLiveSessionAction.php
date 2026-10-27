<?php

namespace App\Actions\Learning;

use App\Jobs\NotifyLiveSessionAudienceJob;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleLiveSessionAction
{
    /**
     * Only a course's own instructor can schedule for it. Students are notified from a queued
     * job, after commit. A session that starts inside the reminder window skips the reminder
     * (the "scheduled" notification already says it's soon).
     */
    public function execute(User $instructor, array $data): LiveSession
    {
        $validated = Validator::make($data, LiveSession::rules())->validate();

        $course = Course::findOrFail($validated['course_id']);

        if ($course->instructor_id !== $instructor->id) {
            throw ValidationException::withMessages([
                'course_id' => ['You can only schedule live sessions for your own courses.'],
            ]);
        }

        if ($course->status !== 'published') {
            throw ValidationException::withMessages([
                'course_id' => ['Publish the course before scheduling a live session for it.'],
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);

        $session = LiveSession::create([
            'course_id' => $course->id,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'starts_at' => $startsAt,
            'duration_minutes' => (int) $validated['duration_minutes'],
            'meeting_url' => $validated['meeting_url'],
            'status' => 'scheduled',
            'reminder_sent_at' => LiveSession::isInsideReminderWindow($startsAt) ? now() : null,
        ]);

        NotifyLiveSessionAudienceJob::dispatch($session->id, 'scheduled')->afterCommit();

        return $session;
    }
}
