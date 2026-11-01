<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\PlatformAnalytics;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PlatformSubscriptionsChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Subscriptions per day';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval ='60s';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year'];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $series = (new PlatformAnalytics)->subscriptionsSeries(AnalyticsRange::lastDays((int) ($this->filter ?? 30)));

        return [
            'datasets' => [
                ['label' => 'New', 'data' => array_values($series['new']), 'borderColor' => '#10b981', 'tension' => 0.3],
                ['label' => 'Canceled', 'data' => array_values($series['canceled']), 'borderColor' => '#ef4444', 'tension' => 0.3],
            ],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->format('M j'), array_keys($series['new'])),
        ];
    }
}
