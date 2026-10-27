<?php

namespace App\Filament\Instructor\Pages;

use App\Actions\Learning\CancelLiveSessionAction;
use App\Actions\Learning\ScheduleLiveSessionAction;
use App\Actions\Learning\UpdateLiveSessionAction;
use App\Models\Course;
use App\Models\LiveSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class LiveSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon =  'heroicon-o-video-camera';
    static string | UnitEnum | null $navigationGroup = 'Community';
    protected static ?string $navigationLabel = 'Live sessions';
    protected string $view = 'filament.instructor.pages.live-sessions';

    public static function formSchema(bool $creating): array
    {
        return [
            Forms\Components\Select::make('course_id')
                ->label('Course')
                ->options(fn () => Course::where('instructor_id', Auth::id())
                    ->where('status', 'published')
                    ->orderBy('title')
                    ->pluck('title', 'id'))
                ->searchable()
                ->required()
                ->visible($creating),
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->rows(3)->maxLength(2000),
            Forms\Components\DateTimePicker::make('starts_at')
                ->label('Starts at')
                ->seconds(false)
                ->required()
                ->minDate($creating ? now() : null)
                ->helperText('Time zone: ' . config('app.timezone')),
            Forms\Components\Select::make('duration_minutes')
                ->label('Duration')
                ->options([30 => '30 minutes', 45 => '45 minutes', 60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours', 180 => '3 hours'])
                ->default(60)
                ->required(),
            Forms\Components\TextInput::make('meeting_url')
                ->label('Zoom / Google Meet link')
                ->url()
                ->required()
                ->maxLength(2048)
                ->placeholder('https://...')
                ->rule('starts_with:https://')
                ->helperText('Must start with https://. Students only see it shortly before the session starts.'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Schedule a live session')
                ->icon('heroicon-o-plus')
                ->visible(fn () => Gate::allows('create', LiveSession::class))
                ->form(fn () => static::formSchema(creating: true))
                ->action(function (array $data, $action) {
                    try {
                        app(ScheduleLiveSessionAction::class)->execute(Auth::user(), $data);
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        $action->halt(); // keep the form open so nothing typed is lost

                        return;
                    }

                    Notification::make()
                        ->title('Live session scheduled')
                        ->body('Students taking this course are being notified.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            // Query-scope layer: only sessions of THIS instructor's courses.
            ->query(
                LiveSession::query()
                    ->with('course')
                    ->whereHas('course', fn (Builder $q) => $q->where('instructor_id', Auth::id()))
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('course.title')->label('Course')->limit(30),
                Tables\Columns\TextColumn::make('starts_at')->label('Starts')->dateTime('M j, Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')->label('Minutes'),
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
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (LiveSession $record) => Gate::allows('update', $record))
                    ->fillForm(fn (LiveSession $record): array => [
                        'title' => $record->title,
                        'description' => $record->description,
                        'starts_at' => $record->starts_at,
                        'duration_minutes' => $record->duration_minutes,
                        'meeting_url' => $record->meeting_url,
                    ])
                    ->form(fn () => static::formSchema(creating: false))
                    ->action(function (LiveSession $record, array $data, $action) {
                        if (! Gate::allows('update', $record)) {
                            Notification::make()->title('You can only edit your own upcoming sessions')->danger()->send();

                            return;
                        }

                        try {
                            app(UpdateLiveSessionAction::class)->execute($record, $data);
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->title('Live session updated')->success()->send();
                    }),

                Action::make('cancelSession')
                    ->label('Cancel session')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LiveSession $record) => Gate::allows('cancel', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Students taking this course will be told it was canceled. This can\'t be undone.')
                    ->action(function (LiveSession $record) {
                        if (! Gate::allows('cancel', $record)) {
                            Notification::make()->title('This session can no longer be canceled')->danger()->send();

                            return;
                        }

                        app(CancelLiveSessionAction::class)->execute($record);
                        Notification::make()->title('Live session canceled')->success()->send();
                    }),
            ])
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('No live sessions yet')
            ->emptyStateDescription('Schedule one and the students taking your course are notified.');
    }
}
