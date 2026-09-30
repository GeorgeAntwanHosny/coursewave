<?php

namespace App\Filament\Instructor\Resources\Courses;

use App\Filament\Instructor\Resources\Courses\Pages\CreateCourse;
use App\Filament\Instructor\Resources\Courses\Pages\EditCourse;
use App\Filament\Instructor\Resources\Courses\Pages\ListCourses;
use App\Filament\Instructor\Resources\Courses\RelationManagers\ModulesRelationManager;
use App\Filament\Instructor\Resources\Courses\Schemas\CourseForm;
use App\Filament\Instructor\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;
    static string | UnitEnum | null $navigationGroup = 'Course Content';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('instructor_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ModulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }

}
