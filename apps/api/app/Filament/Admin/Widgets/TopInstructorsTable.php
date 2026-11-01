<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Services\Analytics\AnalyticsRange;
use App\Support\Money;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopInstructorsTable extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected static ?string $heading = 'Top instructors';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $range = AnalyticsRange::lastDays((int) config('coursewave.analytics.default_days', 30));

                return User::query()
                    ->role('instructor')
                    ->addSelect([
                        'earned_cents' => DB::table('ledger_entries')
                            ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                            ->whereColumn('ledger_entries.instructor_id', 'users.id')
                            ->where('entry_type', 'accrual')
                            ->whereBetween('created_at', [$range->from, $range->to]),
                        'learners' => DB::table('consumption_events')
                            ->selectRaw('COUNT(DISTINCT student_id)')
                            ->whereColumn('consumption_events.instructor_id', 'users.id')
                            ->whereBetween('event_date', [$range->from->toDateString(), $range->to->toDateString()]),
                        'balance_cents' => DB::table('instructor_balances')
                            ->selectRaw('COALESCE(owed_cents + paid_cents, 0)')
                            ->whereColumn('instructor_balances.instructor_id', 'users.id'),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Instructor')->searchable(),
                Tables\Columns\TextColumn::make('earned_cents')
                    ->label('Earned (' . (int) config('coursewave.analytics.default_days', 30) . 'd)')
                    ->formatStateUsing(fn ($state) => Money::usd((int) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('learners')->label('Active learners')->sortable(),
                Tables\Columns\TextColumn::make('balance_cents')
                    ->label('Owed now')
                    ->formatStateUsing(fn ($state) => Money::usd((int) $state))
                    ->sortable(),
            ])
            ->defaultSort('earned_cents', 'desc')
            ->paginated([10])
            ->emptyStateHeading('No instructors yet');
    }
}
