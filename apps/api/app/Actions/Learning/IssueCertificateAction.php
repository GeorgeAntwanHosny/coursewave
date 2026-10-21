<?php

namespace App\Actions\Learning;

use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class IssueCertificateAction
{
    /**
     * Issues the student's certificate for $course if (and only if) every lesson in
     * it is completed. Returns the existing certificate when one was already
     * issued (wasRecentlyCreated = false), the new one when this call created it
     * (wasRecentlyCreated = true), or null when the course isn't complete.
     *
     * Quiz attempts deliberately do not count: course completion is lesson completion.
     */
    public function execute(User $student, Course $course): ?Certificate
    {
        $existing = $this->existing($student, $course);
        if ($existing) {
            return $existing;
        }

        if (! $this->isCourseComplete($student, $course)) {
            return null;
        }

        $course->loadMissing('instructor');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // Nested in a transaction so a unique violation rolls back to a savepoint
                // instead of poisoning an outer Postgres transaction.
                $certificate = DB::transaction(fn () => Certificate::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'certificate_number' => $this->generateNumber(),
                    'student_name' => $student->name,
                    'course_title' => $course->title,
                    'instructor_name' => $course->instructor->name,
                    'issued_at' => now(),
                ]));

                GenerateCertificatePdfJob::dispatch($certificate->id)->afterCommit();

                return $certificate;
            } catch (UniqueConstraintViolationException $e) {
                // Either another request issued it first (return theirs), or the random
                // number collided (loop and draw a new one).
                $existing = $this->existing($student, $course);
                if ($existing) {
                    return $existing;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a unique certificate number.');
    }

    public function isCourseComplete(User $student, Course $course): bool
    {
        $lessonIds = Lesson::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('id');

        if ($lessonIds->isEmpty()) {
            return false; // a course with no lessons can never be "completed"
        }

        $completed = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', 'completed')
            ->count();

        return $completed >= $lessonIds->count();
    }

    protected function existing(User $student, Course $course): ?Certificate
    {
        return Certificate::where('student_id', $student->id)->where('course_id', $course->id)->first();
    }

    /** CW-XXXX-XXXX-XXXX, from an alphabet with no I, O, 0 or 1. ~60 bits of randomness. */
    protected function generateNumber(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];

        for ($g = 0; $g < 3; $g++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $chunk;
        }

        return 'CW-' . implode('-', $groups);
    }
}
