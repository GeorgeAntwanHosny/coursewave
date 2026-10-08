<?php

namespace App\Filament\Admin\Pages;

use App\Models\InstructorBalance;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InstructorBalances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Instructor Balances';

    // Must be non-static
    protected string $view = 'filament.admin.pages.instructor-balances';

    protected function getTableQuery(): Builder
    {
        return InstructorBalance::query()->with('instructor');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('instructor.name')->label('Instructor')->searchable(),
            Tables\Columns\TextColumn::make('owed_cents')->label('Owed')->formatStateUsing(fn ($s) => '$' . number_format($s / 100, 2)),
            Tables\Columns\TextColumn::make('paid_cents')->label('Paid')->formatStateUsing(fn ($s) => '$' . number_format(abs($s) / 100, 2)),
            Tables\Columns\TextColumn::make('outstanding_cents')
                ->label('Outstanding')
                ->state(fn (InstructorBalance $record) => $record->owed_cents + $record->paid_cents)
                ->formatStateUsing(fn ($s) => '$' . number_format($s / 100, 2)),
        ];
    }
}
