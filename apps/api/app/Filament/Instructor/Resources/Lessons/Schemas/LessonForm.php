<?php

namespace App\Filament\Instructor\Resources\Lessons\Schemas;

use App\Models\Module;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('module_id')
                    ->label('Module')
                    ->options(fn () => Module::whereHas('course', fn ($q) => $q->where('instructor_id', Auth::id()))
                        ->with('course')
                        ->get()
                        ->mapWithKeys(fn ($m) => [$m->id => "{$m->course->title} → {$m->title}"]))
                    ->required()
                    ->searchable(),
                TextInput::make('title')->required()->maxLength(255),
                Select::make('type')
                    ->options(['video' => 'Video', 'text' => 'Text'])
                    ->required()
                    ->live(),
                TextInput::make('video_url')
                    ->url()
                    ->visible(fn (callable $get) => $get('type') === 'video'),
                RichEditor::make('body')
                    ->visible(fn (callable $get) => $get('type') === 'text')
                    ->columnSpanFull(),
                TextInput::make('order')->numeric()->default(0),
            ]);
    }
}
