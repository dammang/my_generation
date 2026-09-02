<?php

namespace App\Filament\Resources\PersonEvents\Pages;

use App\Filament\Resources\PersonEvents\PersonEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonEvents extends ListRecords
{
    protected static string $resource = PersonEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
