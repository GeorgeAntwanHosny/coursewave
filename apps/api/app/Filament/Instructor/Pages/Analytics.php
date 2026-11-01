<?php

namespace App\Filament\Instructor\Pages;

use App\Filament\Instructor\Widgets\InstructorCoursePerformance;
use App\Filament\Instructor\Widgets\InstructorEarningsChart;
use App\Filament\Instructor\Widgets\InstructorEngagementChart;
use App\Filament\Instructor\Widgets\InstructorOverviewStats;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Analytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    static string | UnitEnum | null $navigationGroup  = 'Analytics';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Analytics';
    protected string $view = 'filament.instructor.pages.analytics';

    protected function getHeaderWidgets(): array
    {
        return [
            InstructorOverviewStats::class,
            InstructorEarningsChart::class,
            InstructorEngagementChart::class,
            InstructorCoursePerformance::class,
        ];
    }

    public function getHeaderWidgetsColumns():  int | array
    {
        return 2;
    }
}
