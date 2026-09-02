<?php

namespace App\Filament\Resources\Tribes\Schemas;

use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TribeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('native_name'),
                TextInput::make('short_name'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('history')
                    ->columnSpanFull(),
                TextInput::make('logo_media_id')
                    ->numeric(),
                TextInput::make('cover_media_id')
                    ->numeric(),
                TextInput::make('country_code'),
                TextInput::make('region'),
                TextInput::make('primary_place_id')
                    ->numeric(),
                Select::make('default_privacy_level')
                    ->options(PrivacyLevel::class)
                    ->default('tribe')
                    ->required(),
                TextInput::make('people_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('clan_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('graph_version')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options(RecordStatus::class)
                    ->default('active')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
