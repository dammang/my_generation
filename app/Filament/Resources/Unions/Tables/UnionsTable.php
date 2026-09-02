<?php

namespace App\Filament\Resources\Unions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UnionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')
                    ->searchable(),
                TextColumn::make('partner1.id')
                    ->searchable(),
                TextColumn::make('partner2.id')
                    ->searchable(),
                TextColumn::make('union_type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('marriage_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('marriage_date_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('marriage_date_precision')
                    ->badge(),
                TextColumn::make('marriage_date_text')
                    ->searchable(),
                TextColumn::make('marriage_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('marriagePlace.name')
                    ->searchable(),
                TextColumn::make('separation_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('divorce_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('order_index')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('children_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verification_status')
                    ->badge(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verified_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
