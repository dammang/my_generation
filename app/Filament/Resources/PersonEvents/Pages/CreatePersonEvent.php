<?php

namespace App\Filament\Resources\PersonEvents\Pages;

use App\Filament\Resources\PersonEvents\PersonEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonEvent extends CreateRecord
{
    protected static string $resource = PersonEventResource::class;
}
