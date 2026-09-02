<?php

namespace App\Filament\Resources\Unions\Schemas;

use App\Enums\DatePrecision;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Enums\VerificationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                Select::make('partner_1_id')
                    ->relationship('partner1', 'id')
                    ->required(),
                Select::make('partner_2_id')
                    ->relationship('partner2', 'id'),
                Select::make('union_type')
                    ->options(UnionType::class)
                    ->default('marriage')
                    ->required(),
                Select::make('status')
                    ->options(UnionStatus::class)
                    ->default('unknown')
                    ->required(),
                DatePicker::make('marriage_date'),
                DatePicker::make('marriage_date_end'),
                Select::make('marriage_date_precision')
                    ->options(DatePrecision::class)
                    ->default('unknown')
                    ->required(),
                TextInput::make('marriage_date_text'),
                TextInput::make('marriage_year')
                    ->numeric(),
                Select::make('marriage_place_id')
                    ->relationship('marriagePlace', 'name'),
                DatePicker::make('separation_date'),
                DatePicker::make('divorce_date'),
                TextInput::make('order_index')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('children_count')
                    ->required()
                    ->numeric()
                    ->default(0),
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
