<?php

namespace App\Filament\Resources\FamilyBranches\Pages;

use App\Filament\Resources\FamilyBranches\FamilyBranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFamilyBranches extends ListRecords
{
    protected static string $resource = FamilyBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
