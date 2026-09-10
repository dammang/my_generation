<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    /** Nothing is created here: a member arrives by asking and being approved. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
