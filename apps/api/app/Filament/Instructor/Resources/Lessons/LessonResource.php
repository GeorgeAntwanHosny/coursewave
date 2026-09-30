<?php

namespace App\Filament\Instructor\Resources\Lessons;

use App\Filament\Instructor\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Instructor\Resources\Lessons\Pages\EditLesson;
use App\Filament\Instructor\Resources\Lessons\Pages\ListLessons;
use App\Filament\Instructor\Resources\Lessons\RelationManagers\ResourceLinksRelationManager;
use App\Filament\Instructor\Resources\Lessons\Schemas\LessonForm;
use App\Filament\Instructor\Resources\Lessons\Tables\LessonsTable;
use App\Models\Lesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;
    static string | UnitEnum | null $navigationGroup = 'Course Content';
    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('module.course', fn (Builder $q) => $q->where('instructor_id', Auth::id()));
    }

    public static function form(Schema $schema): Schema
    {
        return LessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ResourceLinksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'create' => CreateLesson::route('/create'),
            'edit' => EditLesson::route('/{record}/edit'),
        ];
    }
}
