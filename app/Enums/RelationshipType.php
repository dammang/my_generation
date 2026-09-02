<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Directed, non-partner edges. Spouses live in unions; siblings are derived.
 */
enum RelationshipType: string
{
    use HasLabel;

    case ParentChild = 'parent_child';
    case Guardian = 'guardian';
    case SiblingAsserted = 'sibling_asserted';
    case Godparent = 'godparent';
    case Other = 'other';
}
