<?php

namespace App\Filament\Resources\Relationships\Schemas;

use App\Enums\Certainty;
use App\Enums\DatePrecision;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\VerificationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RelationshipForm
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
                Select::make('related_person_id')
                    ->relationship('relatedPerson', 'id')
                    ->required(),
                Select::make('relationship_type')
                    ->options(RelationshipType::class)
                    ->default('parent_child')
                    ->required(),
                Select::make('relationship_subtype')
                    ->options(RelationshipSubtype::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('custom_label'),
                Toggle::make('is_biological'),
                Select::make('union_id')
                    ->relationship('union', 'id'),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                Select::make('date_precision')
                    ->options(DatePrecision::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('date_text'),
                Select::make('place_id')
                    ->relationship('place', 'name'),
                Select::make('certainty')
                    ->options(Certainty::class)
                    ->default('possible')
                    ->required(),
                Select::make('verification_status')
                    ->options(VerificationStatus::class)
                    ->default('unverified')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
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
