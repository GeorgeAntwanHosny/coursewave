<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PlatformEngagementChart;
use App\Filament\Admin\Widgets\PlatformGrowthStats;
use App\Filament\Admin\Widgets\PlatformHealthStats;
use App\Filament\Admin\Widgets\PlatformRevenueChart;
use App\Filament\Admin\Widgets\PlatformRevenueStats;
use App\Filament\Admin\Widgets\PlatformSubscriptionsChart;
use App\Filament\Admin\Widgets\TopCoursesTable;
use App\Filament\Admin\Widgets\TopInstructorsTable;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Analytics extends Page
{
     protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static string|UnitEnum|null $navigationGroup ='Analytics';
    protected static ?string $navigationLabel = 'Platform analytics';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Platform analytics';
    protected string $view = 'filament.admin.pages.analytics';

    protected function getHeaderWidgets(): array
    {
        return [
            PlatformHealthStats::class,
            PlatformRevenueStats::class,
            PlatformGrowthStats::class,
            PlatformRevenueChart::class,
            PlatformSubscriptionsChart::class,
            PlatformEngagementChart::class,
            TopInstructorsTable::class,
            TopCoursesTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
