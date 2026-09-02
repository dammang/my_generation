<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Who may see the full record. See docs/04-privacy-model.md.
 */
enum PrivacyLevel: string
{
    use HasLabel;

    case Public = 'public';
    case Tribe = 'tribe';
    case Clan = 'clan';
    case Family = 'family';
    case Private = 'private';

    /**
     * Restrictiveness rank: higher means fewer people may see it.
     * Used to cap share links and to pick the stricter of two levels.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Tribe => 1,
            self::Clan => 2,
            self::Family => 3,
            self::Private => 4,
        };
    }

    public function isAtLeastAsStrictAs(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /** The stricter of the two — used when a record inherits from a parent scope. */
    public function strictest(self $other): self
    {
        return $this->rank() >= $other->rank() ? $this : $other;
    }
}
