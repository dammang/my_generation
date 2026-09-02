<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\PrivacyLevel;

/**
 * The privacy *column*. Deciding whether a given viewer may see the record is
 * the job of the policy and PersonVisibilityResolver (Phase 4) — never of the
 * model, and never of the client.
 */
trait HasPrivacyLevel
{
    public function privacyLevel(): PrivacyLevel
    {
        $column = $this->privacyColumn ?? 'privacy_level';
        $value = $this->getAttribute($column);

        if ($value instanceof PrivacyLevel) {
            return $value;
        }

        return PrivacyLevel::tryFrom((string) $value)
            ?? PrivacyLevel::from(config('genealogy.privacy.default_person_level'));
    }

    public function isPublic(): bool
    {
        return $this->privacyLevel() === PrivacyLevel::Public;
    }
}
