<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfileClaims;

use App\Filament\Resources\ProfileClaims\Pages\ListProfileClaims;
use App\Filament\Resources\ProfileClaims\Tables\ProfileClaimsTable;
use App\Models\ProfileClaim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * "This person in the archive is me."
 *
 * The API has been able to approve and reject these since claims shipped, and
 * nothing in the product could reach it — not the panel, not the app. Somebody
 * asked to be recognised and their request sat pending forever, which is the
 * same gap membership requests had.
 *
 * Index only. Editing a claim would let an administrator rewrite what somebody
 * asserted about themselves and then approve their own wording; the decision
 * is the whole action, and it belongs to DecideProfileClaim.
 */
class ProfileClaimResource extends Resource
{
    protected static ?string $model = ProfileClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Profile claim';

    protected static ?string $pluralModelLabel = 'Profile claims';

    public static function table(Table $table): Table
    {
        return ProfileClaimsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // A claim is somebody asserting who they are. An administrator making
        // one on their behalf is not a thing this should support.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileClaims::route('/'),
        ];
    }
}
