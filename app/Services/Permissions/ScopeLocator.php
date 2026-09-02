<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Scope;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves a `{scope_type, scope_ulid}` pair to its Scope row.
 *
 * Clients address scopes by the thing they are — a tribe, a clan, a branch —
 * never by a scope id, which is an internal join key and would be meaningless
 * to a user and useless in a URL.
 */
class ScopeLocator
{
    /** @var array<string, class-string<Model>> */
    private const OWNERS = [
        'tribe' => Tribe::class,
        'clan' => Clan::class,
        'family_branch' => FamilyBranch::class,
    ];

    public function owner(string $type, string $ulid): Model
    {
        $class = self::OWNERS[$type] ?? throw new ModelNotFoundException("Unknown scope type [{$type}].");

        return $class::where('ulid', $ulid)->firstOrFail();
    }

    public function locate(string $type, string $ulid): Scope
    {
        $owner = $this->owner($type, $ulid);

        return $this->forOwner($owner);
    }

    public function forOwner(Model $owner): Scope
    {
        return Scope::where('scopeable_type', $owner->getMorphClass())
            ->where('scopeable_id', $owner->getKey())
            ->firstOrFail();
    }
}
