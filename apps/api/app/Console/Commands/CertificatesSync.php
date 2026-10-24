<?php

namespace App\Console\Commands;

use App\Actions\Learning\IssueCertificateAction;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CertificatesSync extends Command
{
    protected $signature = 'certificates:sync';
    protected $description = 'Issue certificates for completed courses that have none (backfill / missed issues) and re-dispatch PDFs that were never generated. Safe to repeat.';

    public function handle(IssueCertificateAction $issue): int
    {
        $issued = 0;

        // Every (student, course) pair with at least one completed lesson; the action
        // decides whether the course is actually complete and skips existing certificates.
        DB::table('lesson_progress')
            ->where('status', 'completed')
            ->select('student_id', 'course_id')
            ->distinct()
            ->orderBy('student_id')
            ->orderBy('course_id')
            ->cursor()
            ->each(function ($pair) use ($issue, &$issued) {
                $student = User::find($pair->student_id);
                $course = Course::with('instructor')->find($pair->course_id);

                if (! $student || ! $course) {
                    return;
                }

                // Students are told about their certificate; instructors are not flooded with history.
                $certificate = $issue->execute($student, $course, notifyInstructor: false);

                if ($certificate?->wasRecentlyCreated) {
                    $issued++;
                }
            });

        $redispatched = 0;

        Certificate::whereNull('pdf_path')
            ->where('created_at', '<', now()->subMinutes(5))
            ->each(function (Certificate $certificate) use (&$redispatched) {
                GenerateCertificatePdfJob::dispatch($certificate->id); // the action re-checks; a repeat is a no-op
                $redispatched++;
            });

        $this->info("Issued {$issued} certificate(s); re-dispatched {$redispatched} missing PDF(s).");

        return self::SUCCESS;
    }
}
