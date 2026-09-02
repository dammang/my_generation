<?php

namespace App\Filament\Resources\Generations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')
                    ->searchable(),
                TextColumn::make('tribe.name')
                    ->searchable(),
                TextColumn::make('clan.name')
                    ->searchable(),
                TextColumn::make('generation_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generation_name')
                    ->searchable(),
                TextColumn::make('local_name')
                    ->searchable(),
                TextColumn::make('estimated_start_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estimated_end_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
