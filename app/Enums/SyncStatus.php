<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Outcome of a queued offline operation.
 */
enum SyncStatus: string
{
    use HasLabel;

    /** Claimed, but not yet finished. The row is the lock. */
    case Pending = 'pending';

    case Applied = 'applied';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
}
