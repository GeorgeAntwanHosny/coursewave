<?php

namespace App\Filament\Instructor\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\InstructorAnalytics;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class InstructorOverviewStats extends StatsOverviewWidget
{
    // Shown on the Analytics page only, not auto-added to the default dashboard.
    protected static bool $isDiscovered = false;

    // The figures are cached for minutes, so refreshing every few seconds would only re-read the cache.
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $days = (int) config('coursewave.analytics.default_days', 30);
        $range = AnalyticsRange::lastDays($days);
        $analytics = new InstructorAnalytics((int) Auth::id());

        $overview = $analytics->overview($range);
        $earningsSparkline = array_map(fn (int $cents) => round($cents / 100, 2), array_values($analytics->earningsSeries($range)));
        $hours = round($overview['engagement_seconds'] / 3600, 1);

        return [
            Stat::make("Earned ({$days}d)", Money::usd($overview['earned_cents']))
                ->description('Accrued from learner engagement')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($earningsSparkline),

            Stat::make('Outstanding balance', Money::usd($overview['outstanding_cents']))
                ->description('Paid out so far: ' . Money::usd($overview['paid_out_cents']))
                ->descriptionIcon('heroicon-m-clock')
                ->color($overview['outstanding_cents'] > 0 ? 'warning' : 'gray'),

            Stat::make("Active learners ({$days}d)", number_format($overview['active_learners']))
                ->description("{$hours} hours of learning")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make("Lessons completed ({$days}d)", number_format($overview['lessons_completed']))
                ->description('Across your courses')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make("Certificates ({$days}d)", number_format($overview['certificates']))
                ->description('Learners who finished a course')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make("New comments ({$days}d)", number_format($overview['new_comments']))
                ->description($overview['upcoming_live_sessions'] . ' upcoming live session(s)')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('gray'),
        ];
    }
}
