<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseDetailResource;
use App\Http\Resources\CourseListResource;
use App\Models\Course;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->withCount('modules')
            ->with('instructor')
            ->when($request->query('search'), fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate(12);

        return ApiResponse::success([
            'items' => CourseListResource::collection($courses->items()),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'total' => $courses->total(),
            ],
        ], 'Courses retrieved.');
    }

    public function show(string $slug)
    {
        $course = Course::where('slug', $slug)
            ->where('status', 'published')
            ->with(['instructor', 'modules.lessons'])
            ->first();

        if (! $course) {
            return ApiResponse::error('Course not found.', null, 404);
        }

        return ApiResponse::success(new CourseDetailResource($course), 'Course retrieved.');
    }
}
