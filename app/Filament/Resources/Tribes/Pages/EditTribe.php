<?php

namespace App\Filament\Resources\Tribes\Pages;

use App\Filament\Resources\Tribes\TribeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTribe extends EditRecord
{
    protected static string $resource = TribeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
