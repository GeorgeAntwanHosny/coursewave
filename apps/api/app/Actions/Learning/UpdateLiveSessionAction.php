<?php

namespace App\Actions\Learning;

use App\Jobs\NotifyLiveSessionAudienceJob;
use App\Models\LiveSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateLiveSessionAction
{
    /**
     * The caller authorizes (LiveSessionPolicy::update). Students are told only when the time or
     * duration changes — a new title, description or link is read live and needs no push. A
     * reschedule resets the reminder so it fires again for the new time. Fields that aren't
     * passed keep their stored value.
     */
    public function execute(LiveSession $session, array $data): LiveSession
    {
        if ($session->status !== 'scheduled' || $session->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'session' => ['Only upcoming or live sessions can be edited.'],
            ]);
        }

        $newStart = Carbon::parse($data['starts_at'] ?? $session->starts_at);
        $newDuration = (int) ($data['duration_minutes'] ?? $session->duration_minutes);

        // Minute precision: the edit form has no seconds field, so re-saving an untouched
        // time must never look like a reschedule.
        $timeChanged = ! $newStart->copy()->startOfMinute()->equalTo($session->starts_at->copy()->startOfMinute())
            || $newDuration !== $session->duration_minutes;

        // A start time that isn't changing may already be in the past (the session may be live).
        $validated = Validator::make([
            'title' => $data['title'] ?? $session->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $session->description,
            'starts_at' => $newStart->toDateTimeString(),
            'duration_minutes' => $newDuration,
            'meeting_url' => $data['meeting_url'] ?? $session->meeting_url,
        ], LiveSession::rules(requireFuture: $timeChanged, withCourse: false))->validate();

        $session->fill([
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'starts_at' => $timeChanged ? $newStart : $session->starts_at,
            'duration_minutes' => $newDuration,
            'meeting_url' => $validated['meeting_url'],
        ]);

        if ($timeChanged) {
            $session->reminder_sent_at = LiveSession::isInsideReminderWindow($newStart) ? now() : null;
        }

        $session->save();

        if ($timeChanged) {
            NotifyLiveSessionAudienceJob::dispatch($session->id, 'rescheduled')->afterCommit();
        }

        return $session->fresh();
    }
}
