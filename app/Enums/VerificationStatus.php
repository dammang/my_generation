<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Evidential state of a claim, distinct from record lifecycle.
 */
enum VerificationStatus: string
{
    use HasLabel;

    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Disputed = 'disputed';
    case Rejected = 'rejected';

    /** Verified records may not be edited directly without verify permission. */
    public function isLocked(): bool
    {
        return $this === self::Verified;
    }

    /** Rejected records are excluded from trees, search and statistics. */
    public function isVisibleInGraph(): bool
    {
        return $this !== self::Rejected;
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Disputed => 'danger',
            self::Rejected => 'gray',
            self::Unverified => 'secondary',
        };
    }
}
