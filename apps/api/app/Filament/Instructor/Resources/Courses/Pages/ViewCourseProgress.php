<?php

namespace App\Filament\Instructor\Resources\Courses\Pages;


use App\Filament\Instructor\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\LessonProgress;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewCourseProgress extends Page
{
     public Course $record;


    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.instructor.pages.course-progress';

    public array $studentRows = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->record->loadMissing('modules.lessons');

        $lessonIds = $this->record->modules->flatMap->lessons->pluck('id');
        $totalLessons = $lessonIds->count();

        $progress = LessonProgress::whereIn('lesson_id', $lessonIds)
            ->with('student')
            ->get()
            ->groupBy('student_id');

        $this->studentRows = $progress->map(function ($rows) use ($totalLessons) {
            $student = $rows->first()?->student;
            $completed = $rows->where('status', 'completed')->count();

            return [
                'student_name' => $student?->name ?? 'Unknown Student',
                'student_email' => $student?->email ?? 'N/A',
                'completed_lessons' => $completed,
                'total_lessons' => $totalLessons,
                'percent' => $totalLessons > 0 ? (int) round($completed / $totalLessons * 100) : 0,
            ];
        })->sortByDesc('percent')->values()->all();
    }

    public function getTitle(): string|Htmlable
    {
        return "Progress — {$this->record->title}";
    }
}
