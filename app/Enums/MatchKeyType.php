<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Blocking key families used to find duplicate candidates without all-pairs comparison.
 */
enum MatchKeyType: string
{
    use HasLabel;

    case NamePhonetic = 'name_phonetic';
    case NameNormalized = 'name_normalized';
    case NameBirthyear = 'name_birthyear';
    case NamePlace = 'name_place';
    case ParentName = 'parent_name';
    case SpouseName = 'spouse_name';
    case BirthDecadePlace = 'birth_decade_place';
}
