<?php

namespace App\Filament\Resources\FamilyBranches;

use App\Filament\Resources\FamilyBranches\Pages\CreateFamilyBranch;
use App\Filament\Resources\FamilyBranches\Pages\EditFamilyBranch;
use App\Filament\Resources\FamilyBranches\Pages\ListFamilyBranches;
use App\Filament\Resources\FamilyBranches\Schemas\FamilyBranchForm;
use App\Filament\Resources\FamilyBranches\Tables\FamilyBranchesTable;
use App\Models\FamilyBranch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FamilyBranchResource extends Resource
{
    protected static ?string $model = FamilyBranch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Family branch';

    protected static ?string $pluralModelLabel = 'Family branches';

    public static function form(Schema $schema): Schema
    {
        return FamilyBranchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FamilyBranchesTable::configure($table);
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
            'index' => ListFamilyBranches::route('/'),
            'create' => CreateFamilyBranch::route('/create'),
            'edit' => EditFamilyBranch::route('/{record}/edit'),
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
