<?php

namespace App\Filament\Admin\Resources\Lessons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('module.course.title')
                    ->label('Course')
                    ->searchable(),

                TextColumn::make('module.course.instructor.name')
                    ->label('Instructor')
                    ->searchable(),

                TextColumn::make('module.title')
                    ->label('Module'),

                TextColumn::make('title')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                IconColumn::make('quiz.id')
                    ->label('Has Quiz')
                    ->boolean(),
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
