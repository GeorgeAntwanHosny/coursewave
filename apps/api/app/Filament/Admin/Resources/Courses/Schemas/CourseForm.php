<?php

namespace App\Filament\Admin\Resources\Courses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),

            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ])
                ->required(),

            Select::make('instructor_id')
                ->relationship('instructor', 'name')
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}
