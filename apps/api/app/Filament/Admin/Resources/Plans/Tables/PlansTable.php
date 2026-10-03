<?php

namespace App\Filament\Admin\Resources\Plans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('billing_interval')
                    ->badge(),

                TextColumn::make('term_days')
                    ->label('Term (days)'),

                TextColumn::make('price_cents')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),

                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
