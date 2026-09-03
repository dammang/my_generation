<?php

namespace App\Filament\Resources\Generations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GenerationForm
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
                Select::make('clan_id')
                    ->relationship('clan', 'name'),
                TextInput::make('generation_number')
                    ->required()
                    ->numeric(),
                TextInput::make('generation_name'),
                TextInput::make('local_name'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('estimated_start_year')
                    ->numeric(),
                TextInput::make('estimated_end_year')
                    ->numeric(),
            ]);
    }
}
