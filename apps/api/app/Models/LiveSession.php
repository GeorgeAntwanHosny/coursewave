<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'title', 'description', 'starts_at', 'duration_minutes', 'ends_at',
        'meeting_url', 'status', 'canceled_at', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // ends_at always follows starts_at + duration, however the row is written.
        static::saving(function (LiveSession $session) {
            if (! $session->ends_at || $session->isDirty(['starts_at', 'duration_minutes'])) {
                $session->ends_at = $session->starts_at->copy()->addMinutes($session->duration_minutes);
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Validation shared by scheduling and editing. A new/changed start time must be in the future. */
    public static function rules(bool $requireFuture = true, bool $withCourse = true): array
    {
        $startsAt = ['required', 'date'];
        if ($requireFuture) {
            $startsAt[] = 'after:now';
        }

        return array_filter([
            'course_id' => $withCourse ? ['required', 'integer', 'exists:courses,id'] : null,
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => $startsAt,
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            // https only: the link is rendered as an href, so schemes like javascript: must never get in.
            'meeting_url' => ['required', 'url:https', 'max:2048'],
        ]);
    }

    public static function reminderWindowMinutes(): int
    {
        return (int) config('coursewave.live_sessions.reminder_minutes_before');
    }

    /** True when a reminder would be pointless because the session starts within the reminder window. */
    public static function isInsideReminderWindow(Carbon $startsAt): bool
    {
        return $startsAt->lte(now()->addMinutes(self::reminderWindowMinutes()));
    }

    /** Sessions students may see: scheduled, not yet ended, and belonging to a published course. */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('ends_at', '>', now())
            ->whereHas('course', fn (Builder $course) => $course->where('status', 'published'));
    }

    public function joinOpensAt(): Carbon
    {
        return $this->starts_at->copy()->subMinutes((int) config('coursewave.live_sessions.join_opens_minutes_before'));
    }

    public function canJoinNow(): bool
    {
        return $this->status === 'scheduled'
            && now()->gte($this->joinOpensAt())
            && now()->lt($this->ends_at);
    }

    /** The link, only if it is a safe https URL. Anything else is treated as "no link". */
    public function safeMeetingUrl(): ?string
    {
        return str_starts_with((string) $this->meeting_url, 'https://') ? $this->meeting_url : null;
    }

    /** canceled | ended | live | upcoming */
    public function displayStatus(): string
    {
        if ($this->status === 'canceled') {
            return 'canceled';
        }

        if ($this->ends_at->isPast()) {
            return 'ended';
        }

        return $this->starts_at->isPast() ? 'live' : 'upcoming';
    }
}
