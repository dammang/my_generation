<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Biological/recorded sex as held in the genealogy record.
 */
enum Gender: string
{
    use HasLabel;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Unknown = 'unknown';
}
