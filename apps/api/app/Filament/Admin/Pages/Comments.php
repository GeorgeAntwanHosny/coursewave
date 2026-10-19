<?php

namespace App\Filament\Admin\Pages;

use App\Actions\Learning\DeleteCommentAction;
use App\Models\Comment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class Comments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|UnitEnum|null $navigationGroup = 'Moderation';
    protected static ?string $navigationLabel = 'Comments';
    protected string $view = 'filament.admin.pages.comments';

    public function table(Table $table): Table
    {
        return $table
            ->query(Comment::query()->with(['user', 'commentable'])->withCount('replies'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
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
            ->recordActions([
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Comment $record) => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->action(function (Comment $record) {
                        app(DeleteCommentAction::class)->execute($record);
                        Notification::make()->title('Comment deleted')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
