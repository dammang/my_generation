<?php

namespace App\Filament\Resources\Tribes\Pages;

use App\Filament\Resources\Tribes\TribeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTribes extends ListRecords
{
    protected static string $resource = TribeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
