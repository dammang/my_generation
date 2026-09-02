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
                TextInput::make('ulid')
                    ->required(),
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
