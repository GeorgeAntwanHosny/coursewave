<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\LiveSession;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class LiveSessionController extends Controller
{
    /** Upcoming and live sessions for the courses this student is taking. */
    public function index(Request $request)
    {
        $sessions = LiveSession::query()
            ->visibleToStudents()
            ->whereIn('course_id', LessonProgress::where('student_id', $request->user()->id)->select('course_id'))
            ->with('course.instructor')
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        return ApiResponse::success(['items' => LiveSessionResource::collection($sessions)], 'Live sessions retrieved.');
    }

    /** One course's upcoming and live sessions, for any subscriber (so they can be discovered before starting the course). */
    public function forCourse(Request $request, string $slug)
    {
        $course = Course::where('slug', $slug)->where('status', 'published')->first();

        if (! $course) {
            return ApiResponse::error('Course not found.', null, 404);
        }

        $sessions = LiveSession::query()
            ->visibleToStudents()
            ->where('course_id', $course->id)
            ->with('course.instructor')
            ->orderBy('starts_at')
            ->get();

        return ApiResponse::success(['items' => LiveSessionResource::collection($sessions)], 'Live sessions retrieved.');
    }
}
