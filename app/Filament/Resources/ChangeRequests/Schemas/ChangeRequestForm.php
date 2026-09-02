<?php

namespace App\Filament\Resources\ChangeRequests\Schemas;

use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChangeRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ulid')
                    ->required(),
                Select::make('operation')
                    ->options(ChangeRequestOperation::class)
                    ->required(),
                TextInput::make('target_type')
                    ->required(),
                TextInput::make('target_id')
                    ->numeric(),
                TextInput::make('parent_change_request_id')
                    ->numeric(),
                TextInput::make('payload')
                    ->required(),
                TextInput::make('original_snapshot'),
                TextInput::make('diff'),
                Select::make('scope_id')
                    ->relationship('scope', 'id'),
                Textarea::make('reason')
                    ->columnSpanFull(),
                Select::make('source_id')
                    ->relationship('source', 'title'),
                Select::make('status')
                    ->options(ChangeRequestStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('applied_at'),
                TextInput::make('applied_revision_ids'),
                TextInput::make('client_operation_id'),
                TextInput::make('requested_by')
                    ->numeric(),
                TextInput::make('decided_by')
                    ->numeric(),
                DateTimePicker::make('decided_at'),
            ]);
    }
}
