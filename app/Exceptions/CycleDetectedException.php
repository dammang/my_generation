<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A proposed parent-child edge would make somebody their own ancestor.
 *
 * One of the few hard errors, because a cycle makes every traversal below it
 * incorrect — not uncertain, incorrect — and would turn depth-limited queries
 * into unbounded ones if the path guard ever failed.
 */
class CycleDetectedException extends GenealogyRuleException
{
    /** @param  array<int, string>  $path  Display names along the offending loop. */
    public function __construct(public readonly array $path = [])
    {
        parent::__construct(
            $path === []
                ? 'This relationship would create a loop in the family tree.'
                : 'This relationship would create a loop: '.implode(' → ', $path).'.',
            'RELATIONSHIP_CYCLE',
        );
    }
}
