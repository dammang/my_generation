<?php

namespace App\Filament\Resources\DuplicateCandidates\Pages;

use App\Filament\Resources\DuplicateCandidates\DuplicateCandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDuplicateCandidate extends CreateRecord
{
    protected static string $resource = DuplicateCandidateResource::class;
}
