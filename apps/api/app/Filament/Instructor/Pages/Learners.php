<?php

namespace App\Filament\Instructor\Pages;

use App\Models\Course;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class Learners extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    static string | UnitEnum | null $navigationGroup  = 'Analytics';
    protected static ?string $navigationLabel = 'Learners';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.instructor.pages.learners';

    /** @return int[] */
    protected static function myCourseIds(): array
    {
        return Course::where('instructor_id', Auth::id())->pluck('id')->all();
    }

    public function table(Table $table): Table
    {
        return $table
            // Query-scope layer: only students who have started one of THIS instructor's courses,
            // and every figure counts only progress inside those courses. Names only, never emails.
            ->query(function () {
                $ids = static::myCourseIds();

                return User::query()
                    ->role('student')
                    ->whereHas('lessonProgress', fn (Builder $q) => $q->whereIn('course_id', $ids))
                    ->withCount([
                        'lessonProgress as lessons_completed' => fn (Builder $q) => $q->whereIn('course_id', $ids)->where('status', 'completed'),
                        'certificates as certificates_count' => fn (Builder $q) => $q->whereIn('course_id', $ids),
                    ])
                    ->withSum(['lessonProgress as watched_seconds_total' => fn (Builder $q) => $q->whereIn('course_id', $ids)], 'watched_seconds')
                    ->withMax(['lessonProgress as last_active_at' => fn (Builder $q) => $q->whereIn('course_id', $ids)], 'last_engaged_at')
                    ->addSelect([
                        'courses_started' => DB::table('lesson_progress')
                            ->selectRaw('COUNT(DISTINCT course_id)')
                            ->whereColumn('lesson_progress.student_id', 'users.id')
                            ->whereIn('course_id', $ids),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Learner')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('courses_started')->label('Courses')->sortable(),
                Tables\Columns\TextColumn::make('lessons_completed')->label('Lessons done')->sortable(),
                Tables\Columns\TextColumn::make('watched_seconds_total')
                    ->label('Learning time')
                    ->formatStateUsing(fn ($state) => intdiv((int) $state, 3600) . 'h ' . intdiv(((int) $state) % 3600, 60) . 'm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('certificates_count')->label('Certificates')->sortable(),
                Tables\Columns\TextColumn::make('last_active_at')->label('Last active')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('completed')
                    ->label('Completed a course')
                    ->query(fn ($query) => $query->whereIn(
                        'users.id',
                        DB::table('certificates')
                            ->whereIn('course_id', static::myCourseIds())
                            ->select('student_id')
                    )),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->emptyStateHeading('No learners yet')
            ->emptyStateDescription('Learners appear here once they start one of your lessons.');
    }
}
