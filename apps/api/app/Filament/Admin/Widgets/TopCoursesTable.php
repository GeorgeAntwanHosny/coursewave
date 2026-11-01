<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Course;
use App\Services\Analytics\AnalyticsRange;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCoursesTable extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Top courses';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $range = AnalyticsRange::lastDays((int) config('coursewave.analytics.default_days', 30));
                $from = $range->from->toDateString();
                $to = $range->to->toDateString();

                return Course::query()
                    ->with('instructor')
                    ->addSelect([
                        'learners_recent' => DB::table('consumption_events')
                            ->selectRaw('COUNT(DISTINCT student_id)')
                            ->whereColumn('consumption_events.course_id', 'courses.id')
                            ->whereBetween('event_date', [$from, $to]),
                        'engagement_seconds_recent' => DB::table('consumption_events')
                            ->selectRaw('COALESCE(SUM(engagement_seconds), 0)')
                            ->whereColumn('consumption_events.course_id', 'courses.id')
                            ->whereBetween('event_date', [$from, $to]),
                        'certificates_recent' => DB::table('certificates')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('certificates.course_id', 'courses.id')
                            ->whereBetween('issued_at', [$range->from, $range->to]),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Course')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('instructor.name')->label('Instructor'),
                Tables\Columns\TextColumn::make('learners_recent')->label('Active learners')->sortable(),
                Tables\Columns\TextColumn::make('engagement_seconds_recent')
                    ->label('Hours')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 3600, 1))
                    ->sortable(),
                Tables\Columns\TextColumn::make('certificates_recent')->label('Certificates')->sortable(),
            ])
            ->defaultSort('learners_recent', 'desc')
            ->paginated([10])
            ->emptyStateHeading('No courses yet');
    }
}
