<?php

namespace App\Http\Controllers\Api;

use App\Actions\Learning\RecordLessonProgressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\RecordProgressRequest;
use App\Http\Resources\CourseLearnResource;
use App\Http\Resources\LessonLearnResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function courseTree(Request $request, string $slug)
    {
        $course = Course::where('slug', $slug)
            ->where('status', 'published')
            ->with(['instructor', 'modules.lessons.quiz'])
            ->first();

        if (! $course) {
            return ApiResponse::error('Course not found.', null, 404);
        }

        $student = $request->user();
        $lessonIds = $course->modules->flatMap->lessons->pluck('id');

        $progressMap = LessonProgress::where('student_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        foreach ($course->modules as $module) {
            foreach ($module->lessons as $lesson) {
                $lesson->setAttribute('progress_status', $progressMap[$lesson->id]->status ?? 'not_started');
            }
        }

        $totalLessons = $lessonIds->count();
        $completedLessons = $progressMap->where('status', 'completed')->count();
        $completionPercent = $totalLessons > 0 ? (int) round($completedLessons / $totalLessons * 100) : 0;

        return ApiResponse::success([
            'course' => new CourseLearnResource($course),
            'completion_percent' => $completionPercent,
        ], 'Course learning content retrieved.');
    }

    public function lesson(Request $request, Lesson $lesson)
    {
        $lesson->load(['module.course', 'resourceLinks']);

        if ($lesson->module->course->status !== 'published') {
            return ApiResponse::error('Lesson not found.', null, 404);
        }

        $student = $request->user();
        $progress = LessonProgress::where('student_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $siblingLessons = Lesson::query()
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.course_id', $lesson->module->course_id)
            ->orderBy('modules.order')
            ->orderBy('lessons.order')
            ->select('lessons.*')
            ->get();

        $index = $siblingLessons->search(fn ($l) => $l->id === $lesson->id);
        $previousId = $index !== false && $index > 0 ? $siblingLessons[$index - 1]->id : null;
        $nextId = $index !== false && $index < $siblingLessons->count() - 1 ? $siblingLessons[$index + 1]->id : null;

        $lesson->setAttribute('my_progress', $progress);
        $lesson->setAttribute('previous_lesson_id', $previousId);
        $lesson->setAttribute('next_lesson_id', $nextId);

        return ApiResponse::success(new LessonLearnResource($lesson), 'Lesson retrieved.');
    }

    public function recordProgress(RecordProgressRequest $request, Lesson $lesson, \App\Actions\Learning\RecordLessonProgressAction $action)
    {
        $lesson->loadMissing('module.course');

        if ($lesson->module->course->status !== 'published') {
            return ApiResponse::error('Lesson not found.', null, 404);
        }

        $progress = $action->execute($request->user(), $lesson, $request->validated());

        return ApiResponse::success([
            'status' => $progress->status,
            'watched_seconds' => $progress->watched_seconds,
        ], 'Progress recorded.');
    }
}
