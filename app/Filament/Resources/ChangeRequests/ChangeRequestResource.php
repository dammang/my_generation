<?php

namespace App\Filament\Resources\ChangeRequests;

use App\Filament\Resources\ChangeRequests\Pages\CreateChangeRequest;
use App\Filament\Resources\ChangeRequests\Pages\EditChangeRequest;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\ChangeRequests\Schemas\ChangeRequestForm;
use App\Filament\Resources\ChangeRequests\Tables\ChangeRequestsTable;
use App\Models\ChangeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChangeRequestResource extends Resource
{
    protected static ?string $model = ChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Change request';

    protected static ?string $pluralModelLabel = 'Verification queue';

    public static function form(Schema $schema): Schema
    {
        return ChangeRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChangeRequestsTable::configure($table);
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
            'index' => ListChangeRequests::route('/'),
            'create' => CreateChangeRequest::route('/create'),
            'edit' => EditChangeRequest::route('/{record}/edit'),
        ];
    }
}
