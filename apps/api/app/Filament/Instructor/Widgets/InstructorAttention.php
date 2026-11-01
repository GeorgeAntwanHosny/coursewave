<?php

namespace App\Filament\Instructor\Widgets;

use App\Filament\Instructor\Pages\Analytics;
use App\Services\Analytics\AttentionItems;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class InstructorAttention extends Widget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 2;
    protected string $view = 'filament.instructor.widgets.attention';
    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'items' => AttentionItems::forInstructor((int) Auth::id()),
            'analyticsUrl' => Analytics::getUrl(panel: 'instructor'),
        ];
    }
}
