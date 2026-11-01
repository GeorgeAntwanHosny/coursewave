<?php

namespace App\Filament\Instructor\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\InstructorAnalytics;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class InstructorEngagementChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Learning hours per day';

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

    protected function getData(): array
    {
        $series = (new InstructorAnalytics((int) Auth::id()))
            ->engagementSeries(AnalyticsRange::lastDays((int) ($this->filter ?? 30)));

        return [
            'datasets' => [[
                'label' => 'Hours',
                'data' => array_map(fn (int $seconds) => round($seconds / 3600, 2), array_values($series)),
            ]],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->format('M j'), array_keys($series)),
        ];
    }
}
