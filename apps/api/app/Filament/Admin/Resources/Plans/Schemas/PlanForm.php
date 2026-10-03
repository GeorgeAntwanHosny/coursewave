<?php

namespace App\Filament\Admin\Resources\Plans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Select::make('billing_interval')
                ->options([
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'annual' => 'Annual',
                ])
                ->required(),

            TextInput::make('term_days')
                ->numeric()
                ->required()
                ->helperText('Exact days in the term — drives how many recognition slices are created.'),

            TextInput::make('price_cents')
                ->numeric()
                ->required()
                ->helperText('Integer cents, e.g. 1999 = $19.99'),

            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
