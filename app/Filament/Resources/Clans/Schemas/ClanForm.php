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
                // Not a form field: HasUlid assigns this the moment the
                // model is created. A public identifier that an administrator
                // can type by hand is one that can be typed wrong, or typed to
                // collide with another record's — better that it never leaves
                // the server's control.
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
