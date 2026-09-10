<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members;

use App\Enums\MembershipStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Membership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Who is in the archive, and what they told us about themselves.
 *
 * Distinct from the request queue on purpose: that is a page of decisions to
 * make and empties as they are made, this is a roll of the people who belong
 * and stays. Filtered by clan, because a clan is who a committee actually
 * administers and there will be more than one.
 */
class MemberResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'members';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Members';

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    /** Members, not applicants: the queue of requests is its own page. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', MembershipStatus::Active)
            ->with(['user', 'scope.scopeable']);
    }

    public static function getPages(): array
    {
        return ['index' => ListMembers::route('/')];
    }
}
