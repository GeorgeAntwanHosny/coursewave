<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\PlatformAnalytics;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformRevenueStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $days = (int) config('coursewave.analytics.default_days', 30);
        $o = (new PlatformAnalytics)->overview(AnalyticsRange::lastDays($days));

        return [
            Stat::make("Gross sales ({$days}d)", Money::usd($o['gross_sales_cents']))
                ->description('Paid up front, before recognition')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make("Platform fee ({$days}d)", Money::usd($o['platform_fee_cents']))
                ->description('Kept from recognized revenue')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make("Instructor earnings ({$days}d)", Money::usd($o['instructor_accrued_cents']))
                ->description('Accrued to instructors')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make("Paid out ({$days}d)", Money::usd($o['paid_out_cents']))
                ->description('Sent to instructor accounts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Owed to instructors', Money::usd($o['outstanding_owed_cents']))
                ->description('Accrued and not yet paid out')
                ->descriptionIcon('heroicon-m-clock')
                ->color($o['outstanding_owed_cents'] > 0 ? 'warning' : 'gray'),

            Stat::make("Refunded ({$days}d)", Money::usd($o['refunded_cents']))
                ->description('Confirmed refunds to students')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('gray'),
        ];
    }
}
