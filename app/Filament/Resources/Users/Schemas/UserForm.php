<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
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
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                // Required when creating an account and never when editing
                // one. It was required always, and Filament loads the field
                // blank on an edit form because a hash is not something to put
                // back in a box — so validation failed on every save and NO
                // user could be edited at all. Toggling somebody to super
                // admin was impossible through the panel for that reason
                // alone, which reads as "the panel will not let me" rather
                // than "this one field is wrong".
                //
                // Dehydrated only when filled, so saving without touching it
                // leaves the existing password alone rather than replacing it
                // with an empty string. The model casts this to `hashed`, so
                // nothing plain is ever stored.
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : null),
                // The account's own record in the archive.
                //
                // This was a numeric TextInput, which meant setting it
                // required knowing a database id — so in practice the only way
                // an account got linked was somebody else approving its claim.
                // A super admin therefore could not link their own account at
                // all: deciding your own claim is refused, correctly, and
                // there was no other route.
                //
                // Searchable, because "who is person 47" is not a question an
                // administrator should have to answer.
                Select::make('person_id')
                    ->label('Archive record')
                    ->relationship('person', 'display_name')
                    ->searchable()
                    ->preload()
                    ->helperText('The person in the archive this account is. Normally set by approving a claim.'),
                TextInput::make('locale')
                    ->required()
                    ->default('en'),
                TextInput::make('avatar_media_id')
                    ->numeric(),
                Toggle::make('is_super_admin')
                    ->required(),
                Select::make('status')
                    ->options(UserStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('last_active_at'),
            ]);
    }
}
