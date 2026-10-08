<?php

namespace App\Support;

class LargestRemainderSplitter
{
    /**
     * Splits $poolCents across $weights (instructor_id => weight — weights
     * need not be normalized, e.g. raw engagement_seconds sums) using the
     * largest-remainder method from README §4. Pure integer arithmetic
     * throughout, no floats, so there is never a rounding-drift risk at any
     * scale. Every cent of $poolCents is accounted for.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int> instructor_id => amount_cents
     */
    public static function split(int $poolCents, array $weights): array
    {
        $weights = array_filter($weights, fn ($w) => $w > 0);

        if (empty($weights) || $poolCents <= 0) {
            return [];
        }

        $totalWeight = array_sum($weights);
        $floors = [];
        $remainderNumerators = [];

        foreach ($weights as $instructorId => $weight) {
            $raw = $poolCents * $weight;
            $floors[$instructorId] = intdiv($raw, $totalWeight);
            $remainderNumerators[$instructorId] = $raw % $totalWeight;
        }

        $remainderCents = $poolCents - array_sum($floors);

        // Largest fractional remainder first; ties broken by instructor_id
        // ascending, for determinism — same split every time, run after run.
        $ranked = array_keys($remainderNumerators);
        usort($ranked, fn ($a, $b) => $remainderNumerators[$b] <=> $remainderNumerators[$a] ?: $a <=> $b);

        for ($i = 0; $i < $remainderCents; $i++) {
            $floors[$ranked[$i]]++;
        }

        return $floors;
    }
}
