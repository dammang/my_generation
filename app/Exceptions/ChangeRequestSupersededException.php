<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The target record moved between a change request being filed and applied.
 *
 * This is how concurrent edits are handled without locking: the reviewer gets a
 * three-way diff instead of silently overwriting whatever changed in between.
 */
class ChangeRequestSupersededException extends ApiException
{
    /** @param  array<string, array{0: mixed, 1: mixed}>  $conflicts */
    public function __construct(public readonly array $conflicts = [])
    {
        parent::__construct('This record changed after the request was submitted. Review the differences.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'CHANGE_REQUEST_SUPERSEDED';
    }

    public function errors(): array
    {
        return ['conflicts' => array_keys($this->conflicts)];
    }

    /**
     * The three-way diff, as data.
     *
     * Naming the conflicting fields is not enough to resolve a conflict: the
     * reviewer needs to see what the record said when the proposal was filed
     * and what it says now, side by side.
     */
    public function context(): array
    {
        return [
            'conflicts' => array_map(
                fn (string $field) => [
                    'field' => $field,
                    'was' => $this->conflicts[$field][0] ?? null,
                    'now' => $this->conflicts[$field][1] ?? null,
                ],
                array_keys($this->conflicts),
            ),
        ];
    }
}
