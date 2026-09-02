<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a parent-child edge came about.
 */
enum RelationshipSubtype: string
{
    use HasLabel;

    case Biological = 'biological';
    case Adoptive = 'adoptive';
    case Step = 'step';
    case Foster = 'foster';
    case Presumed = 'presumed';
    case Unknown = 'unknown';
    case Custom = 'custom';
}
