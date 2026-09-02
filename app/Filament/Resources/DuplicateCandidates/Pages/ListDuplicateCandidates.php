<?php

namespace App\Filament\Resources\DuplicateCandidates\Pages;

use App\Filament\Resources\DuplicateCandidates\DuplicateCandidateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDuplicateCandidates extends ListRecords
{
    protected static string $resource = DuplicateCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
