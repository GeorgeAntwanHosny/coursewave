<?php

namespace App\Filament\Admin\Pages;

use App\Models\LedgerEntry;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LedgerOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Ledger';
    protected string $view = 'filament.admin.pages.ledger-overview';

    public function getTotalPlatformFeeCents(): int
    {
        return (int) LedgerEntry::where('entry_type', 'platform_fee')->sum('amount_cents');
    }

    public function getTotalAccruedCents(): int
    {
        return (int) LedgerEntry::where('entry_type', 'accrual')->sum('amount_cents');
    }

    public function getTotalPaidCents(): int
    {
        return (int) LedgerEntry::where('entry_type', 'payout')->sum('amount_cents');
    }

    protected function getTableQuery(): Builder
    {
        return LedgerEntry::query()->with('instructor');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')->label('Date')->date()->sortable(),
            Tables\Columns\BadgeColumn::make('entry_type')->label('Type'),
            Tables\Columns\TextColumn::make('instructor.name')->label('Instructor')->placeholder('— platform —'),
            Tables\Columns\TextColumn::make('amount_cents')
                ->label('Amount')
                ->formatStateUsing(fn ($state) => ($state >= 0 ? '+' : '-') . '$' . number_format(abs($state) / 100, 2)),
            Tables\Columns\TextColumn::make('subscription_id')->label('Subscription #'),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('entry_type')
                ->options(['accrual' => 'Accrual', 'payout' => 'Payout', 'platform_fee' => 'Platform fee', 'refund_adjustment' => 'Refund adjustment']),
            Tables\Filters\SelectFilter::make('instructor_id')
                ->relationship('instructor', 'name')
                ->label('Instructor'),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'created_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }
}
