<?php

namespace App\Actions\Learning;

use App\Models\ConsumptionEvent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordLessonProgressAction
{
    // Sanity cap per single heartbeat ping. Not real anti-cheat — a
    // determined client could still send many capped pings quickly — but it
    // stops one obviously-wrong value from inflating a day's engagement.
    // The FormRequest also rejects anything over this outright (422); this
    // clamp is defense-in-depth, same philosophy as the query-scope+policy
    // pattern from Phase 2.
    protected const MAX_HEARTBEAT_SECONDS = 120;

    public function execute(User $student, Lesson $lesson, array $data): LessonProgress
    {
        $lesson->loadMissing('module.course');
        $course = $lesson->module->course;

        $subscription = Subscription::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        // Middleware already guarantees this for the HTTP path; kept here so
        // the action stays safe if ever called directly (e.g. a future job).
        abort_if(! $subscription, 403, 'An active subscription is required.');

        $completed = (bool) ($data['completed'] ?? false);
        $rawWatchedSeconds = (int) ($data['watched_seconds'] ?? 0);
        $watchedSeconds = max(0, min($rawWatchedSeconds, self::MAX_HEARTBEAT_SECONDS));

        $engagementSeconds = $watchedSeconds;
        if ($lesson->type === 'text' && $completed && $engagementSeconds === 0) {
            $engagementSeconds = $this->estimateReadingSeconds($lesson);
        }

        $progress = DB::transaction(function () use ($student, $lesson, $course, $subscription, $engagementSeconds, $completed, $watchedSeconds) {
            if ($engagementSeconds > 0 || $completed) {
                ConsumptionEvent::create([
                    'subscription_id' => $subscription->id,
                    'student_id' => $student->id,
                    'instructor_id' => $course->instructor_id,
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'lesson_type' => $lesson->type,
                    'event_date' => now()->toDateString(),
                    'engagement_seconds' => $engagementSeconds,
                    'completed' => $completed,
                ]);
            }

            $progress = LessonProgress::firstOrNew([
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
            ]);

            $progress->course_id = $course->id;
            $progress->watched_seconds = ($progress->watched_seconds ?? 0) + $watchedSeconds;
            $progress->last_engaged_at = now();

            if ($completed) {
                if ($progress->status !== 'completed') {
                    $progress->completed_at = now();
                }
                $progress->status = 'completed';
            } elseif ($progress->status !== 'completed') {
                $progress->status = 'in_progress';
            }

            $progress->save();

            return $progress;
        });

        // Phase 9: a completion event may finish the course. Issued AFTER the progress
        // transaction, and a failure here must never fail the student's progress save —
        // `certificates:sync` picks up anything missed.
        $certificateIssued = false;

        if ($completed) {
            try {
                $certificate = app(IssueCertificateAction::class)->execute($student, $course);
                $certificateIssued = (bool) $certificate?->wasRecentlyCreated;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $progress->setAttribute('certificate_issued', $certificateIssued);

        return $progress;
    }

    protected function estimateReadingSeconds(Lesson $lesson): int
    {
        $wordCount = str_word_count(strip_tags($lesson->body ?? ''));

        return (int) max(5, ceil($wordCount / 200 * 60)); // ~200 words/minute
    }
}
