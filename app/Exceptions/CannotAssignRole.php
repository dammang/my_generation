<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A role grant that would escalate the granter's own authority, or that they
 * have no standing to make in the target scope.
 */
class CannotAssignRole extends ApiException
{
    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'ROLE_ASSIGNMENT_FORBIDDEN';
    }
}
