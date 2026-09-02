<?php

namespace App\Filament\Resources\FamilyBranches\Pages;

use App\Filament\Resources\FamilyBranches\FamilyBranchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFamilyBranch extends EditRecord
{
    protected static string $resource = FamilyBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
