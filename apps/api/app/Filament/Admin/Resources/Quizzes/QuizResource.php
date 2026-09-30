<?php

namespace App\Filament\Admin\Resources\Quizzes;

use App\Filament\Admin\Resources\Quizzes\Pages\ListQuizzes;
use App\Filament\Admin\Resources\Quizzes\Pages\ViewQuiz;
use App\Filament\Admin\Resources\Quizzes\Tables\QuizzesTable;
use App\Models\Quiz;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Course Content';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return QuizzesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizzes::route('/'),
            'view' => ViewQuiz::route('/{record}'),
        ];
    }
}
