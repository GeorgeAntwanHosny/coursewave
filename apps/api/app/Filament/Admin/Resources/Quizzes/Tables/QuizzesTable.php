<?php

namespace App\Filament\Admin\Resources\Quizzes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuizzesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lesson.module.course.title')
                    ->label('Course'),

                TextColumn::make('lesson.module.course.instructor.name')
                    ->label('Instructor'),

                TextColumn::make('lesson.title')
                    ->label('Lesson'),

                TextColumn::make('title'),

                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Questions'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
