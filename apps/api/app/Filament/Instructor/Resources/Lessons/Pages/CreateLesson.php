<?php

namespace App\Filament\Instructor\Resources\Lessons\Pages;

use App\Filament\Instructor\Resources\Lessons\LessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;
}
