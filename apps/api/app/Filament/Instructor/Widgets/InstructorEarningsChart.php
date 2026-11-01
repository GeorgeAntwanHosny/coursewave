<?php

namespace App\Filament\Instructor\Widgets;

use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\InstructorAnalytics;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class InstructorEarningsChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Earnings per day';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

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
        $series = (new InstructorAnalytics((int) Auth::id()))
            ->earningsSeries(AnalyticsRange::lastDays((int) ($this->filter ?? 30)));

        return [
            'datasets' => [[
                'label' => 'Earned ($)',
                'data' => array_map(fn (int $cents) => round($cents / 100, 2), array_values($series)),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->format('M j'), array_keys($series)),
        ];
    }
}
