<?php

namespace App\Filament\Instructor\Widgets;

use App\Models\Course;
use App\Services\Analytics\AnalyticsRange;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InstructorCoursePerformance extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected static ?string $heading = 'Course performance';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $since = AnalyticsRange::lastDays((int) config('coursewave.analytics.default_days', 30))->from->toDateString();

                // Query-scope layer: only this instructor's courses. Every figure is a correlated
                // subquery, so the table stays sortable and paginated without loading learners.
                return Course::query()
                    ->where('courses.instructor_id', Auth::id())
                    ->addSelect([
                        'students_started' => DB::table('lesson_progress')
                            ->selectRaw('COUNT(DISTINCT student_id)')
                            ->whereColumn('lesson_progress.course_id', 'courses.id'),
                        'lessons_completed_total' => DB::table('lesson_progress')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('lesson_progress.course_id', 'courses.id')
                            ->where('status', 'completed'),
                        'total_lessons' => DB::table('lessons')
                            ->join('modules', 'modules.id', '=', 'lessons.module_id')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('modules.course_id', 'courses.id'),
                        'certificates_issued' => DB::table('certificates')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('certificates.course_id', 'courses.id'),
                        'engagement_seconds_recent' => DB::table('consumption_events')
                            ->selectRaw('COALESCE(SUM(engagement_seconds), 0)')
                            ->whereColumn('consumption_events.course_id', 'courses.id')
                            ->where('event_date', '>=', $since),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Course')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('students_started')->label('Learners')->sortable(),
                Tables\Columns\TextColumn::make('completion_percent')
                    ->label('Avg. completion')
                    ->state(function (Course $record) {
                        $possible = (int) $record->total_lessons * (int) $record->students_started;

                        return $possible > 0 ? round(100 * (int) $record->lessons_completed_total / $possible) : 0;
                    })
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('certificates_issued')->label('Certificates')->sortable(),
                Tables\Columns\TextColumn::make('engagement_seconds_recent')
                    ->label('Hours (' . (int) config('coursewave.analytics.default_days', 30) . 'd)')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 3600, 1))
                    ->sortable(),
            ])
            ->defaultSort('students_started', 'desc')
            ->paginated([10])
            ->emptyStateHeading('No courses yet');
    }
}
