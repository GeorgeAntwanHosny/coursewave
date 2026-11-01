<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\PlatformAnalytics;
use App\Support\Money;
use App\Support\Trend;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The platform's last 7 days, each card compared with the 7 days before. Cancellations and refunds going up show red. */
class PlatformWeekStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $analytics = new PlatformAnalytics;
        $range = AnalyticsRange::lastDays(7);
        $now = $analytics->overview($range);
        $before = $analytics->overview($range->previous());

        $feeSparkline = array_map(fn (int $cents) => round($cents / 100, 2), array_values($analytics->revenueSeries($range)['platform']));
        $newSparkline = array_values($analytics->subscriptionsSeries($range)['new']);

        $stat = function (string $label, string $value, int|float $current, int|float $previous, string $good = 'up') {
            $trend = Trend::compare($current, $previous, $good);

            return Stat::make($label, $value)
                ->description($trend['text'])
                ->descriptionIcon($trend['icon'])
                ->color($trend['color']);
        };

        return [
            $stat('Gross sales (7d)', Money::usd($now['gross_sales_cents']), $now['gross_sales_cents'], $before['gross_sales_cents']),

            $stat('Platform fee (7d)', Money::usd($now['platform_fee_cents']), $now['platform_fee_cents'], $before['platform_fee_cents'])
                ->chart($feeSparkline),

            $stat('Refunded (7d)', Money::usd($now['refunded_cents']), $now['refunded_cents'], $before['refunded_cents'], 'down'),

            $stat('New subscriptions (7d)', number_format($now['new_subscriptions']), $now['new_subscriptions'], $before['new_subscriptions'])
                ->chart($newSparkline),

            $stat('Cancellations (7d)', number_format($now['canceled_subscriptions']), $now['canceled_subscriptions'], $before['canceled_subscriptions'], 'down'),

            $stat('Active learners (7d)', number_format($now['active_learners']), $now['active_learners'], $before['active_learners']),
        ];
    }
}
