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
}
