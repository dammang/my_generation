<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Reviewer decision on a possible duplicate pair.
 */
enum DuplicateStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Merged = 'merged';
    case KeptSeparate = 'kept_separate';
    case Dismissed = 'dismissed';
}
