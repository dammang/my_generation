<?php

namespace App\Filament\Resources\FamilyBranches\Schemas;

use App\Enums\RecordStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FamilyBranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                Select::make('tribe_id')
                    ->relationship('tribe', 'name')
                    ->required(),
                Select::make('clan_id')
                    ->relationship('clan', 'name'),
                TextInput::make('ancestor_person_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('native_name'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('origin_place_id')
                    ->relationship('originPlace', 'name'),
                TextInput::make('current_place_id')
                    ->numeric(),
                TextInput::make('current_region'),
                TextInput::make('cover_media_id')
                    ->numeric(),
                TextInput::make('people_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('generation_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(RecordStatus::class)
                    ->default('active')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
