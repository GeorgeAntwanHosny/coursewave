<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Analytics;
use App\Services\Analytics\AttentionItems;
use Filament\Widgets\Widget;

class PlatformAttention extends Widget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 1; // alarms come first for an admin
    protected string $view = 'filament.admin.widgets.attention';
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'items' => AttentionItems::forAdmin(),
            'analyticsUrl' => Analytics::getUrl(panel: 'admin'),
        ];
    }
}
