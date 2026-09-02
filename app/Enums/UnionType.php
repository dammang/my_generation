<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Kind of partnership. Customary marriage is first-class, not an 'other'.
 */
enum UnionType: string
{
    use HasLabel;

    case Marriage = 'marriage';
    case CustomaryMarriage = 'customary_marriage';
    case CivilPartnership = 'civil_partnership';
    case Partnership = 'partnership';
    case Unknown = 'unknown';
}
