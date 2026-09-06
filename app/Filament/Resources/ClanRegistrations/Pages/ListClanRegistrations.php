<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanRegistrations\Pages;

use App\Filament\Resources\ClanRegistrations\ClanRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListClanRegistrations extends ListRecords
{
    protected static string $resource = ClanRegistrationResource::class;
}
