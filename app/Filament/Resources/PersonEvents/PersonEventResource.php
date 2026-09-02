<?php

namespace App\Filament\Resources\PersonEvents;

use App\Filament\Resources\PersonEvents\Pages\CreatePersonEvent;
use App\Filament\Resources\PersonEvents\Pages\EditPersonEvent;
use App\Filament\Resources\PersonEvents\Pages\ListPersonEvents;
use App\Filament\Resources\PersonEvents\Schemas\PersonEventForm;
use App\Filament\Resources\PersonEvents\Tables\PersonEventsTable;
use App\Models\PersonEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PersonEventResource extends Resource
{
    protected static ?string $model = PersonEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Genealogy';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Chronicle';

    public static function form(Schema $schema): Schema
    {
        return PersonEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonEventsTable::configure($table);
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
            'index' => ListPersonEvents::route('/'),
            'create' => CreatePersonEvent::route('/create'),
            'edit' => EditPersonEvent::route('/{record}/edit'),
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
