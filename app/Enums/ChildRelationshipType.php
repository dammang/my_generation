<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a child belongs to a union.
 */
enum ChildRelationshipType: string
{
    use HasLabel;

    case Biological = 'biological';
    case Adoptive = 'adoptive';
    case Step = 'step';
    case Foster = 'foster';
    case Unknown = 'unknown';
}
