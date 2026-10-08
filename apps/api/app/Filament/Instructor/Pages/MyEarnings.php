<?php

namespace App\Filament\Instructor\Pages;

use App\Models\LedgerEntry;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MyEarnings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon =  'heroicon-o-banknotes';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected string $view = 'filament.instructor.pages.my-earnings';

    public function getOwedCents(): int
    {
        return (int) (Auth::user()->balance?->owed_cents ?? 0);
    }

    public function getPaidCents(): int
    {
        return (int) (Auth::user()->balance?->paid_cents ?? 0);
    }

    public function getOutstandingCents(): int
    {
        return $this->getOwedCents() + $this->getPaidCents();
    }

    protected function getTableQuery(): Builder
    {
        return LedgerEntry::query()->where('instructor_id', Auth::id())->with('subscription');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')->label('Date')->date()->sortable(),
            Tables\Columns\BadgeColumn::make('entry_type')->label('Type'),
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
                ->options(['accrual' => 'Accrual', 'payout' => 'Payout', 'refund_adjustment' => 'Refund adjustment']),
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
