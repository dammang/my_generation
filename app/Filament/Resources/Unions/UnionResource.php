<?php

namespace App\Filament\Resources\Unions;

use App\Filament\Resources\Unions\Pages\CreateUnion;
use App\Filament\Resources\Unions\Pages\EditUnion;
use App\Filament\Resources\Unions\Pages\ListUnions;
use App\Filament\Resources\Unions\Schemas\UnionForm;
use App\Filament\Resources\Unions\Tables\UnionsTable;
use App\Models\Union;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class UnionResource extends Resource
{
    protected static ?string $model = Union::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Genealogy';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return UnionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnionsTable::configure($table);
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
            'index' => ListUnions::route('/'),
            'create' => CreateUnion::route('/create'),
            'edit' => EditUnion::route('/{record}/edit'),
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
