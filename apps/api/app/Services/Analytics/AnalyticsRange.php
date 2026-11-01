<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

final class AnalyticsRange
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    /** The last $days days, today included: from the start of the first day to the end of today. */
    public static function lastDays(int $days): self
    {
        $days = max(1, min(366, $days));

        return new self(now()->subDays($days - 1)->startOfDay(), now()->endOfDay());
    }

    /** The same number of days directly before this range, for "versus the previous period" comparisons. */
    public function previous(): self
    {
        $days = count($this->dates());
        $to = $this->from->copy()->subDay()->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        return new self($from, $to);
    }

    /** @return string[] every date in the range, oldest first, as Y-m-d */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from->copy()->startOfDay(); $day->lte($this->to); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /** Stable per-day cache key. */
    public function key(): string
    {
        return $this->from->toDateString() . '_' . $this->to->toDateString();
    }

    /**
     * Turns sparse query results into a complete series: every date in the range appears,
     * with 0 where there was no data, so charts have no gaps.
     *
     * @param  array<string, int|float>  $byDate
     * @return array<string, int|float>
     */
    public function fill(array $byDate): array
    {
        $series = [];

        foreach ($this->dates() as $date) {
            $series[$date] = $byDate[$date] ?? 0;
        }

        return $series;
    }
}
