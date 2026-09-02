<?php

namespace App\Filament\Resources\Clans;

use App\Filament\Resources\Clans\Pages\CreateClan;
use App\Filament\Resources\Clans\Pages\EditClan;
use App\Filament\Resources\Clans\Pages\ListClans;
use App\Filament\Resources\Clans\Schemas\ClanForm;
use App\Filament\Resources\Clans\Tables\ClansTable;
use App\Models\Clan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ClanResource extends Resource
{
    protected static ?string $model = Clan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClansTable::configure($table);
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
            'index' => ListClans::route('/'),
            'create' => CreateClan::route('/create'),
            'edit' => EditClan::route('/{record}/edit'),
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
