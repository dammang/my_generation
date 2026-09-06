<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a request to start a clan has got to.
 *
 * Withdrawn is separate from rejected on purpose: somebody changing their mind
 * and somebody being told no are different events, and a family archive is
 * partly a record of who decided what.
 */
enum ClanRegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
