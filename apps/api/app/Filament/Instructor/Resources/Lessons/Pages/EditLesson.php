<?php

namespace App\Filament\Instructor\Resources\Lessons\Pages;

use App\Filament\Instructor\Resources\Lessons\LessonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLesson extends EditRecord
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
