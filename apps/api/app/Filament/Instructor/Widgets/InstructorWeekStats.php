<?php

namespace App\Filament\Instructor\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\InstructorAnalytics;
use App\Support\Money;
use App\Support\Trend;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** The instructor's last 7 days, each card compared with the 7 days before. Registered on the panel, so it is the Dashboard. */
class InstructorWeekStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 1;
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $analytics = new InstructorAnalytics((int) Auth::id());
        $range = AnalyticsRange::lastDays(7);
        $now = $analytics->overview($range);
        $before = $analytics->overview($range->previous());
        $sparkline = array_map(fn (int $cents) => round($cents / 100, 2), array_values($analytics->earningsSeries($range)));

        $stat = function (string $label, string $value, int|float $current, int|float $previous) {
            $trend = Trend::compare($current, $previous);

            return Stat::make($label, $value)
                ->description($trend['text'])
                ->descriptionIcon($trend['icon'])
                ->color($trend['color']);
        };

        return [
            $stat('Earned (7d)', Money::usd($now['earned_cents']), $now['earned_cents'], $before['earned_cents'])
                ->chart($sparkline),

            $stat('Active learners (7d)', number_format($now['active_learners']), $now['active_learners'], $before['active_learners']),

            $stat('Learning hours (7d)', number_format($now['engagement_seconds'] / 3600, 1), $now['engagement_seconds'], $before['engagement_seconds']),

            $stat('Lessons completed (7d)', number_format($now['lessons_completed']), $now['lessons_completed'], $before['lessons_completed']),

            $stat('Certificates (7d)', number_format($now['certificates']), $now['certificates'], $before['certificates']),

            // Comments are neither good nor bad news by themselves, so this card stays neutral.
            Stat::make('New comments (7d)', number_format($now['new_comments']))
                ->description(Trend::compare($now['new_comments'], $before['new_comments'], 'none')['text'])
                ->descriptionIcon(Trend::compare($now['new_comments'], $before['new_comments'], 'none')['icon'])
                ->color('gray'),
        ];
    }
}
