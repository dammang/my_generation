<?php

namespace App\Filament\Resources\Unions\Pages;

use App\Filament\Resources\Unions\UnionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnions extends ListRecords
{
    protected static string $resource = UnionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
