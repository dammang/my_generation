<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Edge kinds in the derived family_edges adjacency table.
 *
 * Backed by int rather than string: family_edges is the hot path for every tree
 * traversal, and a TINYINT keeps both covering indexes small enough to stay
 * resident in the buffer pool at millions of rows.
 */
enum EdgeKind: int
{
    use HasLabel;

    case Biological = 1;
    case Adoptive = 2;
    case Step = 3;
    case Foster = 4;
    case Guardian = 5;

    public static function fromSubtype(RelationshipSubtype $subtype): self
    {
        return match ($subtype) {
            RelationshipSubtype::Adoptive => self::Adoptive,
            RelationshipSubtype::Step => self::Step,
            RelationshipSubtype::Foster => self::Foster,
            default => self::Biological,
        };
    }

    /** Adoptive and step edges are rendered dashed in the tree. */
    public function isDashed(): bool
    {
        return $this !== self::Biological;
    }
}
