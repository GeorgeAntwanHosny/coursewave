<?php

namespace App\Filament\Instructor\Resources\Quizzes;

use App\Filament\Instructor\Resources\Quizzes\Pages\CreateQuiz;
use App\Filament\Instructor\Resources\Quizzes\Pages\EditQuiz;
use App\Filament\Instructor\Resources\Quizzes\Pages\ListQuizzes;
use App\Filament\Instructor\Resources\Quizzes\Schemas\QuizForm;
use App\Filament\Instructor\Resources\Quizzes\Tables\QuizzesTable;
use App\Models\Quiz;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    static string | UnitEnum | null $navigationGroup = 'Course Content';
    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('lesson.module.course', fn (Builder $q) => $q->where('instructor_id', Auth::id()));
    }

    public static function form(Schema $schema): Schema
    {
        return QuizForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizzesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizzes::route('/'),
            'create' => CreateQuiz::route('/create'),
            'edit' => EditQuiz::route('/{record}/edit'),
        ];
    }
}
