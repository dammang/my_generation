<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * State of a user's membership request in a scope.
 */
enum MembershipStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Left = 'left';
}
