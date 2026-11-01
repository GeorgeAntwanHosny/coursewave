<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\PlatformAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformGrowthStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $days = (int) config('coursewave.analytics.default_days', 30);
        $o = (new PlatformAnalytics)->overview(AnalyticsRange::lastDays($days));
        $hours = round($o['engagement_seconds'] / 3600, 1);

        return [
            Stat::make('Active subscriptions', number_format($o['active_subscriptions']))
                ->description("{$o['new_subscriptions']} new, {$o['canceled_subscriptions']} canceled ({$days}d)")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make("Active learners ({$days}d)", number_format($o['active_learners']))
                ->description("{$hours} hours of learning")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Students', number_format($o['students_total']))
                ->description(number_format($o['instructors_total']).' instructors')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            Stat::make('Published courses', number_format($o['published_courses']))
                ->description(number_format($o['certificates'])." certificates ({$days}d)")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),
        ];
    }
}
