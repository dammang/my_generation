<?php

namespace App\Filament\Resources\DuplicateCandidates\Pages;

use App\Filament\Resources\DuplicateCandidates\DuplicateCandidateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDuplicateCandidate extends EditRecord
{
    protected static string $resource = DuplicateCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
