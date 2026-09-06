<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanRegistrations;

use App\Filament\Resources\ClanRegistrations\Pages\ListClanRegistrations;
use App\Filament\Resources\ClanRegistrations\Tables\ClanRegistrationsTable;
use App\Models\ClanRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * "I would like to start the Guite clan here."
 *
 * Index only, and creating one here is refused. A registration is somebody
 * asking; an administrator filling one in on their behalf, or editing what was
 * asked before approving it, is not a thing this should support — the decision
 * is the whole action.
 */
class ClanRegistrationResource extends Resource
{
    protected static ?string $model = ClanRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Clan registration';

    protected static ?string $pluralModelLabel = 'Clan registrations';

    public static function table(Table $table): Table
    {
        return ClanRegistrationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClanRegistrations::route('/'),
        ];
    }
}
