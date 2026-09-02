<?php

namespace App\Filament\Resources\Stories\Schemas;

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use App\Enums\VerificationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->columnSpanFull(),
                TextInput::make('summary'),
                TextInput::make('person_id')
                    ->numeric(),
                Select::make('family_branch_id')
                    ->relationship('familyBranch', 'name'),
                Select::make('clan_id')
                    ->relationship('clan', 'name'),
                Select::make('tribe_id')
                    ->relationship('tribe', 'name'),
                Select::make('author_id')
                    ->relationship('author', 'name'),
                TextInput::make('language')
                    ->required()
                    ->default('en'),
                Select::make('story_type')
                    ->options(StoryType::class)
                    ->default('narrative')
                    ->required(),
                TextInput::make('era_start_year')
                    ->numeric(),
                TextInput::make('era_end_year')
                    ->numeric(),
                Select::make('visibility')
                    ->options(PrivacyLevel::class)
                    ->default('family')
                    ->required(),
                Select::make('verification_status')
                    ->options(VerificationStatus::class)
                    ->default('unverified')
                    ->required(),
                TextInput::make('view_count')
                    ->required()
                    ->numeric()
                    ->default(0),
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
