<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\ReconcilePayoutBatchJob;
use App\Models\PayoutBatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class PayoutBatches extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Payouts';
    protected string $view = 'filament.admin.pages.payout-batches';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runNow')
                ->label('Run payouts now')
                ->requiresConfirmation()
                ->modalDescription('Claims a batch for every eligible instructor (period ending yesterday). Safe to repeat — already-claimed periods are skipped.')
                ->action(function () {
                    Artisan::call('payouts:run');
                    Notification::make()->title('Payout run started')->body(trim(Artisan::output()))->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PayoutBatch::query()->with('instructor')->withCount('attemptLog'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('instructor.name')->label('Instructor')->searchable(),
                Tables\Columns\TextColumn::make('period_start')->label('From')->date(),
                Tables\Columns\TextColumn::make('period_end')->label('To')->date(),
                Tables\Columns\TextColumn::make('amount_cents')->label('Amount')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'succeeded' => 'success',
                    'failed' => 'danger',
                    'uncertain', 'reconciling' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('attempt_log_count')->label('Logged attempts'),
                Tables\Columns\TextColumn::make('failure_reason')->label('Note')->limit(40)->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('provider_transfer_id')->label('Transfer')->placeholder('—')->copyable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'submitted' => 'Submitted', 'succeeded' => 'Succeeded',
                    'failed' => 'Failed', 'uncertain' => 'Uncertain', 'reconciling' => 'Reconciling', 'skipped' => 'Skipped',
                ]),
            ])
            ->recordActions([
                Action::make('attempts')
                    ->label('Attempts')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->slideOver()
                    ->modalHeading(fn (PayoutBatch $record) => "Payout #{$record->id} — provider attempts")
                    ->modalContent(fn (PayoutBatch $record) => view('filament.admin.pages.payout-attempts', [
                        'attempts' => $record->attemptLog()->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('reconcile')
                    ->label('Reconcile now')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PayoutBatch $record) => $record->status === 'uncertain')
                    ->requiresConfirmation()
                    ->action(function (PayoutBatch $record) {
                        ReconcilePayoutBatchJob::dispatch($record->id);
                        Notification::make()->title('Reconciliation queued')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
