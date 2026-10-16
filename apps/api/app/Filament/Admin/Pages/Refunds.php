<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\ReconcileRefundJob;
use App\Models\Refund;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class Refunds extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Refunds';
    protected string $view = 'filament.admin.pages.refunds';

    public function table(Table $table): Table
    {
        return $table
            ->query(Refund::query()->with('student')->withCount('attemptLog'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('student.name')->label('Student')->searchable(),
                Tables\Columns\TextColumn::make('subscription_id')->label('Subscription #'),
                Tables\Columns\TextColumn::make('amount_cents')->label('Amount')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('reason')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'succeeded' => 'success',
                    'failed' => 'danger',
                    'uncertain', 'reconciling' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('attempt_log_count')->label('Logged attempts'),
                Tables\Columns\TextColumn::make('failure_reason')->label('Note')->limit(40)->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('provider_refund_id')->label('Refund')->placeholder('—')->copyable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'submitted' => 'Submitted', 'succeeded' => 'Succeeded',
                    'failed' => 'Failed', 'uncertain' => 'Uncertain', 'reconciling' => 'Reconciling',
                ]),
            ])
            ->recordActions([
                Action::make('attempts')
                    ->label('Attempts')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->slideOver()
                    ->modalHeading(fn (Refund $record) => "Refund #{$record->id} — provider attempts")
                    ->modalContent(fn (Refund $record) => view('filament.admin.pages.refund-attempts', [
                        'attempts' => $record->attemptLog()->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('reconcile')
                    ->label('Reconcile now')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Refund $record) => $record->status === 'uncertain')
                    ->requiresConfirmation()
                    ->action(function (Refund $record) {
                        ReconcileRefundJob::dispatch($record->id);
                        Notification::make()->title('Reconciliation queued')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
