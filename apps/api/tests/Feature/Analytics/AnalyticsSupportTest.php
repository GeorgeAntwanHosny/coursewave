<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\AnalyticsRange;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_range_covers_exactly_the_requested_days_ending_today(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));
        $range = AnalyticsRange::lastDays(5);
        $dates = $range->dates();

        $this->assertCount(5, $dates);
        $this->assertSame(now()->toDateString(), end($dates));
        $this->assertSame(now()->subDays(4)->toDateString(), $dates[0]);
        $this->assertTrue($range->from->isStartOfDay());
        $this->assertTrue($range->to->isSameDay(now()));
    }

    public function test_the_day_count_is_clamped(): void
    {
        $this->assertCount(1, AnalyticsRange::lastDays(0)->dates());
        $this->assertCount(366, AnalyticsRange::lastDays(5000)->dates());
    }

    public function test_fill_adds_zeros_for_days_without_data_and_ignores_dates_outside_the_range(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));
        $range = AnalyticsRange::lastDays(3);
        $yesterday = now()->subDay()->toDateString();

        $series = $range->fill([$yesterday => 700, '1999-01-01' => 5]);

        $this->assertCount(3, $series);
        $this->assertSame(700, $series[$yesterday]);
        $this->assertSame(0, $series[now()->toDateString()]);
        $this->assertArrayNotHasKey('1999-01-01', $series);
    }

    public function test_the_cache_key_changes_with_the_range(): void
    {
        $this->assertNotSame(AnalyticsRange::lastDays(7)->key(), AnalyticsRange::lastDays(30)->key());
        $this->assertSame(AnalyticsRange::lastDays(7)->key(), AnalyticsRange::lastDays(7)->key());
    }

    public function test_money_formats_integer_cents(): void
    {
        $this->assertSame('$0.00', Money::usd(0));
        $this->assertSame('$12.34', Money::usd(1234));
        $this->assertSame('$1,000,000.00', Money::usd(100_000_000));
        $this->assertSame('-$5.00', Money::usd(-500));
        $this->assertSame('$0.00', Money::usd(null));
    }
}
