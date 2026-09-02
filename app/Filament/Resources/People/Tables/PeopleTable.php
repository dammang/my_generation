<?php

namespace App\Filament\Resources\People\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PeopleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('middle_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('native_name')
                    ->searchable(),
                TextColumn::make('nickname')
                    ->searchable(),
                TextColumn::make('display_name')
                    ->searchable(),
                TextColumn::make('sort_name')
                    ->searchable(),
                TextColumn::make('gender')
                    ->badge(),
                TextColumn::make('birth_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_date_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_date_precision')
                    ->badge(),
                TextColumn::make('birth_date_text')
                    ->searchable(),
                TextColumn::make('birth_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('birthPlace.name')
                    ->searchable(),
                TextColumn::make('death_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('death_date_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('death_date_precision')
                    ->badge(),
                TextColumn::make('death_date_text')
                    ->searchable(),
                TextColumn::make('death_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deathPlace.name')
                    ->searchable(),
                TextColumn::make('burialPlace.name')
                    ->searchable(),
                IconColumn::make('is_living')
                    ->boolean(),
                TextColumn::make('living_reviewed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('profileMedia.id')
                    ->searchable(),
                TextColumn::make('coverMedia.id')
                    ->searchable(),
                TextColumn::make('tribe.name')
                    ->searchable(),
                TextColumn::make('clan.name')
                    ->searchable(),
                TextColumn::make('familyBranch.name')
                    ->searchable(),
                TextColumn::make('generation.id')
                    ->searchable(),
                TextColumn::make('privacy_level')
                    ->badge(),
                TextColumn::make('verification_status')
                    ->badge(),
                IconColumn::make('has_open_dispute')
                    ->boolean(),
                TextColumn::make('merged_into_person_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('external_ref')
                    ->searchable(),
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
