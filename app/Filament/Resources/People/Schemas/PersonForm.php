<?php

namespace App\Filament\Resources\People\Schemas;

use App\Enums\DatePrecision;
use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                TextInput::make('first_name'),
                TextInput::make('middle_name'),
                TextInput::make('last_name'),
                TextInput::make('native_name'),
                TextInput::make('nickname'),
                TextInput::make('display_name')
                    ->required(),
                TextInput::make('sort_name'),
                Select::make('gender')
                    ->options(Gender::class)
                    ->default('unknown')
                    ->required(),
                DatePicker::make('birth_date'),
                DatePicker::make('birth_date_end'),
                Select::make('birth_date_precision')
                    ->options(DatePrecision::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('birth_date_text'),
                TextInput::make('birth_year')
                    ->numeric(),
                Select::make('birth_place_id')
                    ->relationship('birthPlace', 'name'),
                DatePicker::make('death_date'),
                DatePicker::make('death_date_end'),
                Select::make('death_date_precision')
                    ->options(DatePrecision::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('death_date_text'),
                TextInput::make('death_year')
                    ->numeric(),
                Select::make('death_place_id')
                    ->relationship('deathPlace', 'name'),
                Select::make('burial_place_id')
                    ->relationship('burialPlace', 'name'),
                Toggle::make('is_living')
                    ->required(),
                DateTimePicker::make('living_reviewed_at'),
                Textarea::make('biography')
                    ->columnSpanFull(),
                Select::make('profile_media_id')
                    ->relationship('profileMedia', 'id'),
                Select::make('cover_media_id')
                    ->relationship('coverMedia', 'id'),
                Select::make('tribe_id')
                    ->relationship('tribe', 'name'),
                Select::make('clan_id')
                    ->relationship('clan', 'name'),
                Select::make('family_branch_id')
                    ->relationship('familyBranch', 'name'),
                Select::make('generation_id')
                    ->relationship('generation', 'id'),
                Select::make('privacy_level')
                    ->options(PrivacyLevel::class)
                    ->default('family')
                    ->required(),
                Select::make('verification_status')
                    ->options(VerificationStatus::class)
                    ->default('unverified')
                    ->required(),
                Toggle::make('has_open_dispute')
                    ->required(),
                TextInput::make('merged_into_person_id')
                    ->numeric(),
                TextInput::make('external_ref'),
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
