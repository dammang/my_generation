<?php

namespace App\Filament\Resources\DuplicateCandidates;

use App\Filament\Resources\DuplicateCandidates\Pages\CreateDuplicateCandidate;
use App\Filament\Resources\DuplicateCandidates\Pages\EditDuplicateCandidate;
use App\Filament\Resources\DuplicateCandidates\Pages\ListDuplicateCandidates;
use App\Filament\Resources\DuplicateCandidates\Schemas\DuplicateCandidateForm;
use App\Filament\Resources\DuplicateCandidates\Tables\DuplicateCandidatesTable;
use App\Models\DuplicateCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DuplicateCandidateResource extends Resource
{
    protected static ?string $model = DuplicateCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Duplicate candidate';

    protected static ?string $pluralModelLabel = 'Possible duplicates';

    public static function form(Schema $schema): Schema
    {
        return DuplicateCandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DuplicateCandidatesTable::configure($table);
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
            'index' => ListDuplicateCandidates::route('/'),
            'create' => CreateDuplicateCandidate::route('/create'),
            'edit' => EditDuplicateCandidate::route('/{record}/edit'),
        ];
    }
}
