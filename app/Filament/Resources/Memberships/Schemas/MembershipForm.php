<?php

namespace App\Filament\Resources\Memberships\Schemas;

use App\Enums\MembershipStatus;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Select::make('scope_id')
                    ->relationship('scope', 'path')
                    ->searchable()
                    ->required(),
                // Not approved_by / approved_at: those are set by
                // DecideMembership, which also writes the audit log entry and
                // invalidates the applicant's cached scopes. Editing status
                // here directly would change who can see what without either
                // of those happening.
                Select::make('status')
                    ->options(MembershipStatus::class)
                    ->default(MembershipStatus::Pending)
                    ->required(),
            ]);
    }
}
