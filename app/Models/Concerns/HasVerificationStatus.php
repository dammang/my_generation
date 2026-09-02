<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasVerificationStatus
{
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    /** Verified records may not be edited directly without verify permission. */
    public function isLocked(): bool
    {
        return $this->verification_status->isLocked();
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Verified);
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereIn('verification_status', [
            VerificationStatus::Unverified,
            VerificationStatus::Pending,
        ]);
    }

    /** Rejected records are excluded from trees, search and statistics. */
    public function scopeInGraph(Builder $query): Builder
    {
        return $query->where('verification_status', '!=', VerificationStatus::Rejected);
    }
}
