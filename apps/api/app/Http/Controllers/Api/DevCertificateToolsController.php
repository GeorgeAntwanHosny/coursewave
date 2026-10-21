<?php

namespace App\Http\Controllers\Api;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Actions\Learning\IssueCertificateAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * LOCAL/TESTING ONLY. Lets a developer finish courses as the logged-in student so
 * the certificate flow can be exercised without clicking through every lesson.
 *
 * Deliberately does NOT write consumption_events: those drive revenue recognition
 * and instructor payouts, and fabricated engagement would create fake money splits.
 */
class DevCertificateToolsController extends Controller
{
    public function completeCourses(
        Request $request,
        IssueCertificateAction $issue,
        GenerateCertificatePdfAction $generatePdf
    ) {
        if ($blocked = $this->guard($request)) {
            return $blocked;
        }

        $student = $request->user();
        $count = max(1, min(5, (int) $request->input('count', 2)));
        $withPdf = $request->boolean('generate_pdf');

        // Filter nulls: "NOT IN (1, NULL)" matches nothing in SQL. course_id is null
        // for certificates whose course was deleted.
        $alreadyCertified = Certificate::where('student_id', $student->id)
            ->pluck('course_id')->filter()->values()->all();

        $courses = Course::where('status', 'published')
            ->whereNotIn('id', $alreadyCertified)
            ->whereHas('modules.lessons')
            ->orderBy('id')
            ->limit($count)
            ->get();

        $results = [];

        foreach ($courses as $course) {
            $lessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))->get();

            DB::transaction(function () use ($lessons, $student, $course) {
                foreach ($lessons as $lesson) {
                    LessonProgress::updateOrCreate(
                        ['student_id' => $student->id, 'lesson_id' => $lesson->id],
                        [
                            'course_id' => $course->id,
                            'status' => 'completed',
                            'completed_at' => now(),
                            'last_engaged_at' => now(),
                        ]
                    );
                }
            });

            $certificate = $issue->execute($student, $course);
            $pdfError = null;

            if ($certificate && $withPdf) {
                try {
                    $generatePdf->execute($certificate);
                    $certificate->refresh();
                } catch (\Throwable $e) {
                    $pdfError = $e->getMessage(); // surfaced so a rendering problem is visible, not silent
                }
            }

            $results[] = [
                'course' => ['id' => $course->id, 'title' => $course->title, 'slug' => $course->slug],
                'lessons_completed' => $lessons->count(),
                'certificate' => $certificate ? new CertificateResource($certificate->loadMissing('course')) : null,
                'certificate_newly_issued' => (bool) $certificate?->wasRecentlyCreated,
                'pdf_error' => $pdfError,
            ];
        }

        $message = $courses->isEmpty()
            ? 'No more published courses with lessons left to complete for this student.'
            : "Completed {$courses->count()} course(s) and ran the real certificate flow.";

        return ApiResponse::success(['courses' => $results], $message);
    }

    /** Removes THIS student's certificates (and PDFs) and lesson progress so a test can be repeated. */
    public function reset(Request $request)
    {
        if ($blocked = $this->guard($request)) {
            return $blocked;
        }

        $student = $request->user();
        $disk = Storage::disk(GenerateCertificatePdfAction::DISK);

        $certificates = Certificate::where('student_id', $student->id)->get();
        $certificates->each(function (Certificate $certificate) use ($disk) {
            if ($certificate->pdf_path) {
                $disk->delete($certificate->pdf_path);
            }
        });

        $deletedCertificates = Certificate::where('student_id', $student->id)->delete();
        $deletedProgress = LessonProgress::where('student_id', $student->id)->delete();

        return ApiResponse::success([
            'certificates_deleted' => $deletedCertificates,
            'lesson_progress_deleted' => $deletedProgress,
        ], 'Your certificates and lesson progress were cleared.');
    }

    protected function guard(Request $request): ?JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return ApiResponse::error('Not found.', null, 404);
        }

        if (! $request->user()->hasRole('student')) {
            return ApiResponse::error('This tool is for student accounts only.', null, 403);
        }

        return null;
    }
}
