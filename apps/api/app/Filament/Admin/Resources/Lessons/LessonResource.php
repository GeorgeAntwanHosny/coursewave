<?php

namespace App\Filament\Admin\Resources\Lessons;

use App\Filament\Admin\Resources\Lessons\Pages\ListLessons;
use App\Filament\Admin\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Admin\Resources\Lessons\Tables\LessonsTable;
use App\Models\Lesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Course Content';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return LessonsTable::configure($table);
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
            'index' => ListLessons::route('/'),
            'view' => ViewLesson::route('/{record}'),
        ];
    }
}
