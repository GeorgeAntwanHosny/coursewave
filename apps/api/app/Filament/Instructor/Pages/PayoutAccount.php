<?php

namespace App\Filament\Instructor\Pages;

use App\Actions\Billing\CreateOrResumePayoutOnboardingAction;
use App\Actions\Billing\RefreshPayoutAccountStatusAction;
use App\Models\InstructorPayoutAccount;
use App\Models\PayoutBatch;
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

class PayoutAccount extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon =  'heroicon-o-building-library';
    static string | UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Payouts';
    protected string $view = 'filament.instructor.pages.payout-account';

    public function mount(): void
    {
        // Returning from provider onboarding: pick up the new status automatically.
        $account = $this->getAccount();

        if ($account && ! $account->payouts_enabled) {
            try {
                app(RefreshPayoutAccountStatusAction::class)->execute($account);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function getAccount(): ?InstructorPayoutAccount
    {
        return InstructorPayoutAccount::where('instructor_id', Auth::id())->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label(fn () => $this->getAccount() ? 'Continue onboarding' : 'Connect payout account')
                ->visible(fn () => ! $this->getAccount()?->payouts_enabled)
                ->action(function () {
                    try {
                        $url = app(CreateOrResumePayoutOnboardingAction::class)
                            ->execute(Auth::user(), static::getUrl(), static::getUrl());
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Could not start onboarding')->body('Please try again shortly.')->danger()->send();

                        return;
                    }

                    return redirect()->away($url);
                }),

            Action::make('refresh')
                ->label('Refresh status')
                ->color('gray')
                ->visible(fn () => (bool) $this->getAccount())
                ->action(function () {
                    try {
                        app(RefreshPayoutAccountStatusAction::class)->execute($this->getAccount());
                        Notification::make()->title('Status updated')->success()->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Could not refresh status')->danger()->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PayoutBatch::query()->where('instructor_id', Auth::id()))
            ->columns([
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
                Tables\Columns\TextColumn::make('completed_at')->label('Paid at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No payouts yet');
    }
}
