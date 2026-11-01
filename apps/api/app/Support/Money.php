<?php

namespace App\Support;

final class Money
{
    /** Display only. All stored money is integer cents (convention #1). */
    public static function usd(int|float|string|null $cents): string
    {
        $cents = (int) $cents;

        return ($cents < 0 ? '-' : '') . '$' . number_format(abs($cents) / 100, 2);
    }
}
