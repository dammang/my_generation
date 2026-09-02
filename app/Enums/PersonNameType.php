<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Why an alternate spelling exists. Critical for search and duplicate detection.
 */
enum PersonNameType: string
{
    use HasLabel;

    case Birth = 'birth';
    case Alternate = 'alternate';
    case Native = 'native';
    case Historical = 'historical';
    case Translated = 'translated';
    case Religious = 'religious';
    case Married = 'married';
    case Nickname = 'nickname';
    case Romanization = 'romanization';
}
