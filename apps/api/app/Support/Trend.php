<?php

namespace App\Support;

final class Trend
{
    /**
     * Compares a figure with the same figure one period earlier, for a dashboard card.
     *
     * $good says which direction is good news: 'up' (earnings), 'down' (cancellations, refunds),
     * or 'none' (neutral, always gray).
     *
     * @return array{text: string, icon: string, color: string}
     */
    public static function compare(int|float $current, int|float $previous, string $good = 'up'): array
    {
        if ($previous == 0 && $current == 0) {
            return ['text' => 'No activity in either period', 'icon' => 'heroicon-m-minus', 'color' => 'gray'];
        }

        if ($previous == 0) {
            return [
                'text' => 'New: nothing in the previous 7 days',
                'icon' => 'heroicon-m-arrow-trending-up',
                'color' => self::color('up', $good),
            ];
        }

        $percent = (int) round(($current - $previous) / $previous * 100);

        if ($percent === 0) {
            return ['text' => 'Same as the previous 7 days', 'icon' => 'heroicon-m-minus', 'color' => 'gray'];
        }

        $direction = $percent > 0 ? 'up' : 'down';

        return [
            'text' => ($percent > 0 ? '+' : '') . $percent . '% vs previous 7 days',
            'icon' => $direction === 'up' ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
            'color' => self::color($direction, $good),
        ];
    }

    private static function color(string $direction, string $good): string
    {
        if ($good === 'none') {
            return 'gray';
        }

        return $direction === $good ? 'success' : 'danger';
    }
}
