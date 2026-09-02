<?php

namespace App\Filament\Resources\ChangeRequests\Pages;

use App\Filament\Resources\ChangeRequests\ChangeRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChangeRequest extends CreateRecord
{
    protected static string $resource = ChangeRequestResource::class;
}
