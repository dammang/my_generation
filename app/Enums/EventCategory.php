<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Grouping for event types, used for timeline icons and filters.
 */
enum EventCategory: string
{
    use HasLabel;

    case Vital = 'vital';
    case Family = 'family';
    case Religious = 'religious';
    case Education = 'education';
    case Work = 'work';
    case Migration = 'migration';
    case Military = 'military';
    case Civic = 'civic';
    case Other = 'other';
}
