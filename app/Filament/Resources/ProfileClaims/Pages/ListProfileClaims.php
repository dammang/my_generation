<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfileClaims\Pages;

use App\Filament\Resources\ProfileClaims\ProfileClaimResource;
use Filament\Resources\Pages\ListRecords;

class ListProfileClaims extends ListRecords
{
    protected static string $resource = ProfileClaimResource::class;
}
