<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Account state.
 */
enum UserStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
