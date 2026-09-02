<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A child was added to a person who has more than one union, without saying
 * which. Guessing would attach the child to the wrong marriage.
 */
class AmbiguousUnionException extends GenealogyRuleException
{
    /** @param  array<int, array{ulid: string, label: string}>  $choices */
    public function __construct(public readonly array $choices = [])
    {
        parent::__construct(
            'This person has more than one union. Say which one the child belongs to.',
            'UNION_AMBIGUOUS',
        );
    }

    public function errors(): array
    {
        return ['union_ulid' => array_map(
            fn (array $choice) => $choice['label'].' ('.$choice['ulid'].')',
            $this->choices,
        )];
    }
}
