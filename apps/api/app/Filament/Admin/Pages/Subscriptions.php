<?php

namespace App\Filament\Admin\Pages;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Subscriptions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Subscriptions';
    protected string $view = 'filament.admin.pages.subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->with(['student', 'plan'])
                    ->withSum(
                        ['recognitionSlices as refundable_cents' => fn ($q) => $q
                            ->where('status', 'pending')
                            ->where('slice_date', '>=', now()->toDateString())],
                        'amount_cents'
                    )
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('student.name')->label('Student')->searchable(),
                Tables\Columns\TextColumn::make('plan.name')->label('Plan'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'active' => 'success',
                    'canceled' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('total_amount_cents')->label('Paid')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('refundable_cents')->label('Refundable now')
                    ->formatStateUsing(fn ($state) => '$' . number_format(((int) $state) / 100, 2)),
                Tables\Columns\TextColumn::make('ends_at')->label('Ends')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active', 'canceled' => 'Canceled', 'pending' => 'Pending', 'expired' => 'Expired',
                ]),
            ])
            ->recordActions([
                Action::make('cancelAndRefund')
                    ->label('Cancel & refund')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Subscription $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel subscription and refund unused days?')
                    ->modalDescription(fn (Subscription $record) => 'The student loses access immediately. Refund of $'
                        . number_format(CancelSubscriptionAction::refundableCents($record) / 100, 2)
                        . ' goes to the original payment method. Days already recognized are not refunded and instructors are not clawed back.')
                    ->action(function (Subscription $record) {
                        $refund = app(CancelSubscriptionAction::class)->execute($record, 'admin_cancel', Auth::id());

                        Notification::make()
                            ->title('Subscription canceled')
                            ->body($refund
                                ? 'Refund of $' . number_format($refund->amount_cents / 100, 2) . ' queued.'
                                : 'Nothing was left to refund.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
