<?php

namespace App\Filament\Resources\PersonEvents\Schemas;

use App\Enums\DatePrecision;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PersonEventForm
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
                Select::make('person_id')
                    ->relationship('person', 'id')
                    ->required(),
                Select::make('event_type_id')
                    ->relationship('eventType', 'id')
                    ->required(),
                TextInput::make('union_id')
                    ->numeric(),
                TextInput::make('title'),
                Textarea::make('description')
                    ->columnSpanFull(),
                DatePicker::make('event_date'),
                DatePicker::make('event_date_end'),
                Select::make('event_date_precision')
                    ->options(DatePrecision::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('event_date_text'),
                TextInput::make('event_year')
                    ->numeric(),
                Select::make('place_id')
                    ->relationship('place', 'name'),
                Select::make('from_place_id')
                    ->relationship('fromPlace', 'name'),
                Select::make('to_place_id')
                    ->relationship('toPlace', 'name'),
                Select::make('privacy_level')
                    ->options(PrivacyLevel::class),
                Select::make('verification_status')
                    ->options(VerificationStatus::class)
                    ->default('unverified')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('verified_by')
                    ->numeric(),
                DateTimePicker::make('verified_at'),
            ]);
    }
}
