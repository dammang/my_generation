<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Current state of a partnership.
 */
enum UnionStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Separated = 'separated';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Annulled = 'annulled';
    case Ended = 'ended';
    case Unknown = 'unknown';
}
