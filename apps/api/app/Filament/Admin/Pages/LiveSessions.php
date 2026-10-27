<?php

namespace App\Filament\Admin\Pages;

use App\Actions\Learning\CancelLiveSessionAction;
use App\Models\LiveSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LiveSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon =  'heroicon-o-video-camera';
    static string | UnitEnum | null $navigationGroup = 'Community';
    protected static ?string $navigationLabel = 'Live sessions';
    protected string $view = 'filament.admin.pages.live-sessions';

    public function table(Table $table): Table
    {
        return $table
            ->query(LiveSession::query()->with('course.instructor'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('course.title')->label('Course')->limit(30),
                Tables\Columns\TextColumn::make('course.instructor.name')->label('Instructor')->searchable(),
                Tables\Columns\TextColumn::make('starts_at')->label('Starts')->dateTime('M j, Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (LiveSession $record) => $record->displayStatus())
                    ->color(fn (string $state) => match ($state) {
                        'upcoming' => 'info',
                        'live' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming and live only')
                    ->query(fn (Builder $q) => $q->where('status', 'scheduled')->where('ends_at', '>', now())),
            ])
            ->recordActions([
                // Moderation only: admins cancel, they don't author or edit an instructor's sessions.
                Action::make('cancelSession')
                    ->label('Cancel session')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LiveSession $record) => Gate::allows('cancel', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Students taking this course will be told it was canceled. This can\'t be undone.')
                    ->action(function (LiveSession $record) {
                        app(CancelLiveSessionAction::class)->execute($record);
                        Notification::make()->title('Live session canceled')->success()->send();
                    }),
            ])
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('No live sessions yet');
    }
}
