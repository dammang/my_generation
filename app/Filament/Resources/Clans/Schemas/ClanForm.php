<?php

namespace App\Filament\Resources\Clans\Schemas;

use App\Enums\RecordStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClanForm
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
                Select::make('parent_clan_id')
                    ->relationship('parentClan', 'name'),
                TextInput::make('path')
                    ->required(),
                TextInput::make('depth')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('level_label'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('native_name'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('history')
                    ->columnSpanFull(),
                TextInput::make('logo_media_id')
                    ->numeric(),
                TextInput::make('cover_media_id')
                    ->numeric(),
                TextInput::make('people_count')
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
