<?php

namespace App\Filament\Instructor\Resources\Quizzes\Schemas;

use App\Models\Lesson;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class QuizForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('lesson_id')
                    ->label('Lesson')
                    ->options(fn () => Lesson::whereHas('module.course', fn ($q) => $q->where('instructor_id', Auth::id()))
                        ->whereDoesntHave('quiz')
                        ->with('module')
                        ->get()
                        ->mapWithKeys(fn ($l) => [$l->id => "{$l->module->title} → {$l->title}"]))
                    ->required()
                    ->searchable(),
                TextInput::make('title')->required()->maxLength(255),
                Repeater::make('questions')
                    ->relationship()
                    ->schema([
                        Textarea::make('question')->required()->rows(2),
                        TextInput::make('order')->numeric()->default(0),
                        Repeater::make('options')
                            ->relationship()
                            ->schema([
                                TextInput::make('option_text')->required(),
                                Toggle::make('is_correct')->label('Correct answer'),
                            ])
                            ->columns(2)
                            ->minItems(2)
                            ->defaultItems(2),
                    ])
                    ->columnSpanFull()
                    ->minItems(1),
            ]);
    }
}
