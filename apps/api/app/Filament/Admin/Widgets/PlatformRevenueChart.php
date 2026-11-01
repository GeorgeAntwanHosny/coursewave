<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\PlatformAnalytics;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PlatformRevenueChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Recognized revenue per day';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): ?array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]];
    }

    protected function getData(): array
    {
        $series = (new PlatformAnalytics)->revenueSeries(AnalyticsRange::lastDays((int) ($this->filter ?? 30)));
        $dollars = fn (array $byDate) => array_map(fn (int $cents) => round($cents / 100, 2), array_values($byDate));

        return [
            'datasets' => [
                ['label' => 'Instructors ($)', 'data' => $dollars($series['instructors']), 'backgroundColor' => '#3b82f6'],
                ['label' => 'Platform fee ($)', 'data' => $dollars($series['platform']), 'backgroundColor' => '#8b5cf6'],
            ],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->format('M j'), array_keys($series['platform'])),
        ];
    }
}
