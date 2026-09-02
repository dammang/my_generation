<?php

namespace App\Filament\Resources\Tribes;

use App\Filament\Resources\Tribes\Pages\CreateTribe;
use App\Filament\Resources\Tribes\Pages\EditTribe;
use App\Filament\Resources\Tribes\Pages\ListTribes;
use App\Filament\Resources\Tribes\Schemas\TribeForm;
use App\Filament\Resources\Tribes\Tables\TribesTable;
use App\Models\Tribe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TribeResource extends Resource
{
    protected static ?string $model = Tribe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TribeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TribesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTribes::route('/'),
            'create' => CreateTribe::route('/create'),
            'edit' => EditTribe::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
