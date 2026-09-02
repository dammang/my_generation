<?php

namespace App\Filament\Resources\DuplicateCandidates\Schemas;

use App\Enums\DuplicateStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DuplicateCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                Select::make('person_a_id')
                    ->relationship('personA', 'id')
                    ->required(),
                Select::make('person_b_id')
                    ->relationship('personB', 'id')
                    ->required(),
                TextInput::make('score')
                    ->required()
                    ->numeric(),
                TextInput::make('signals'),
                Select::make('status')
                    ->options(DuplicateStatus::class)
                    ->default('open')
                    ->required(),
                TextInput::make('reviewed_by')
                    ->numeric(),
                DateTimePicker::make('reviewed_at'),
            ]);
    }
}
