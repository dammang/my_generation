<?php

namespace App\Filament\Resources\Generations\Pages;

use App\Filament\Resources\Generations\GenerationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneration extends EditRecord
{
    protected static string $resource = GenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
