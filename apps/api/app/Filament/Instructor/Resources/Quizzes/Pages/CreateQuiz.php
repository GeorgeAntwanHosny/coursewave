<?php

namespace App\Filament\Instructor\Resources\Quizzes\Pages;

use App\Filament\Instructor\Resources\Quizzes\QuizResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuiz extends CreateRecord
{
    protected static string $resource = QuizResource::class;
}
