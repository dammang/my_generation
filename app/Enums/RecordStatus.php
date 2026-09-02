<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lifecycle of a record, distinct from its evidential state.
 */
enum RecordStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
    case Hidden = 'hidden';
}
