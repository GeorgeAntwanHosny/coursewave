<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Analytics\PlatformAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** What needs a human right now. Anything above zero is highlighted. */
class PlatformHealthStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $h = (new PlatformAnalytics)->health();

        $stat = fn (string $label, int $count, string $hint, string $badColor) => Stat::make($label, number_format($count))
            ->description($count > 0 ? $hint : 'All clear')
            ->descriptionIcon($count > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
            ->color($count > 0 ? $badColor : 'success');

        return [
            $stat('Payouts unconfirmed', $h['payouts_uncertain'], 'Check Stripe, then reconcile', 'warning'),
            $stat('Payouts failed (7d)', $h['payouts_failed_7d'], 'Declined; amounts roll into the next run', 'danger'),
            $stat('Refunds unconfirmed', $h['refunds_uncertain'], 'Verify in Stripe before resending', 'warning'),
            $stat('Refunds failed', $h['refunds_failed'], 'Resolve in the Stripe dashboard', 'danger'),
        ];
    }
}
