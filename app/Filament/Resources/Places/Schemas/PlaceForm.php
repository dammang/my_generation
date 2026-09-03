<?php

namespace App\Filament\Resources\Places\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlaceForm
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
                Select::make('parent_id')
                    ->relationship('parent', 'name'),
                TextInput::make('path')
                    ->required(),
                TextInput::make('depth')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('name')
                    ->required(),
                TextInput::make('native_name'),
                TextInput::make('type')
                    ->required()
                    ->default('other'),
                TextInput::make('country_code'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('historical_names'),
                TextInput::make('people_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
