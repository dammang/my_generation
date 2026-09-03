<?php

namespace App\Filament\Resources\Sources\Schemas;

use App\Enums\PrivacyLevel;
use App\Enums\SourceReliability;
use App\Enums\SourceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SourceForm
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
                TextInput::make('title')
                    ->required(),
                Select::make('source_type')
                    ->options(SourceType::class)
                    ->default('other')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('author'),
                TextInput::make('publisher'),
                TextInput::make('publication_year')
                    ->numeric(),
                TextInput::make('repository'),
                TextInput::make('url')
                    ->url(),
                Select::make('media_id')
                    ->relationship('media', 'id'),
                TextInput::make('informant_person_id')
                    ->numeric(),
                Select::make('reliability')
                    ->options(SourceReliability::class)
                    ->default('secondary')
                    ->required(),
                Select::make('tribe_id')
                    ->relationship('tribe', 'name'),
                TextInput::make('clan_id')
                    ->numeric(),
                Select::make('privacy_level')
                    ->options(PrivacyLevel::class)
                    ->default('tribe')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
