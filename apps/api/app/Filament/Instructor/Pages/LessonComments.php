<?php

namespace App\Filament\Instructor\Pages;

use App\Actions\Learning\CreateCommentAction;
use App\Actions\Learning\DeleteCommentAction;
use App\Models\Comment;
use App\Models\Lesson;
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

class LessonComments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    static string | UnitEnum | null $navigationGroup = 'Community';
    protected static ?string $navigationLabel = 'Lesson comments';
    protected string $view = 'filament.instructor.pages.lesson-comments';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Comment::query()
                    ->with(['user', 'commentable'])
                    ->withCount('replies')
                    // Only comments on lessons in THIS instructor's courses (query-scope layer).
                    ->whereHasMorph('commentable', [Lesson::class], fn (Builder $q) => $q
                        ->whereHas('module.course', fn (Builder $c) => $c->where('instructor_id', Auth::id())))
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Author')->searchable(),
                Tables\Columns\TextColumn::make('body')->limit(80)->tooltip(fn (Comment $record) => $record->body)->searchable(),
                Tables\Columns\TextColumn::make('lesson')->label('Lesson')
                    ->state(fn (Comment $record) => $record->commentable?->title),
                Tables\Columns\TextColumn::make('kind')->label('Type')->badge()
                    ->state(fn (Comment $record) => $record->parent_id ? 'Reply' : 'Comment')
                    ->color(fn (string $state) => $state === 'Reply' ? 'gray' : 'primary'),
                Tables\Columns\TextColumn::make('replies_count')->label('Replies'),
                Tables\Columns\TextColumn::make('created_at')->label('Posted')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unanswered')
                    ->label('Needs my reply')
                    ->query(fn (Builder $q) => $q->whereNull('parent_id')
                        ->whereDoesntHave('replies', fn (Builder $r) => $r->where('user_id', Auth::id()))),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Comment $record) => $record->parent_id === null)
                    ->form([
                        Forms\Components\Textarea::make('body')->label('Your reply')->required()->maxLength(2000)->rows(4),
                    ])
                    ->action(function (Comment $record, array $data) {
                        $lesson = $record->commentable;

                        if (! $lesson instanceof Lesson || ! Gate::allows('create', [Comment::class, $lesson])) {
                            Notification::make()->title('You can only reply on your own courses')->danger()->send();

                            return;
                        }

                        try {
                            app(CreateCommentAction::class)->execute(Auth::user(), $lesson, $data['body'], $record->id);
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Reply posted')->success()->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Comment $record) => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->modalDescription('If this comment has replies, it stays in the thread as "deleted".')
                    ->action(function (Comment $record) {
                        app(DeleteCommentAction::class)->execute($record);
                        Notification::make()->title('Comment deleted')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No comments on your lessons yet');
    }
}
