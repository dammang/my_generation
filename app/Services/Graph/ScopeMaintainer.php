<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Scope;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Model;

/**
 * Maintains the `scopes` spine and its materialised paths.
 *
 * Every tribe, clan and family branch gets exactly one scope row. The path
 * (/1/14/57/) is what turns "does this user administer this record" into a
 * prefix comparison against the user's cached admin paths, instead of a
 * recursive query on every authorization check.
 */
class ScopeMaintainer
{
    /** Creates the scope row if absent, and keeps its parent, depth and path correct. */
    public function sync(Model $owner): Scope
    {
        $parent = $this->parentScopeFor($owner);

        $scope = Scope::firstOrNew([
            'scopeable_type' => $owner->getMorphClass(),
            'scopeable_id' => $owner->getKey(),
        ]);

        $scope->parent_scope_id = $parent?->getKey();
        $scope->depth = $parent === null ? 0 : $parent->depth + 1;
        $scope->save();

        // The path contains the row's own id, so it can only be written once
        // the row exists.
        $path = ($parent?->path ?? '/').$scope->getKey().'/';

        if ($scope->path !== $path) {
            $scope->forceFill(['path' => $path])->save();
            $this->repathDescendants($scope);
        }

        return $scope;
    }

    /**
     * Re-parenting a clan moves every scope beneath it. Rare, but if the paths
     * are not rewritten every permission check below that point silently
     * answers with the old hierarchy.
     */
    public function repathDescendants(Scope $scope): void
    {
        $children = Scope::where('parent_scope_id', $scope->getKey())->get();

        foreach ($children as $child) {
            $path = $scope->path.$child->getKey().'/';

            if ($child->path !== $path || $child->depth !== $scope->depth + 1) {
                $child->forceFill(['path' => $path, 'depth' => $scope->depth + 1])->save();
            }

            $this->repathDescendants($child);
        }
    }

    private function parentScopeFor(Model $owner): ?Scope
    {
        return match (true) {
            $owner instanceof Tribe => null,

            $owner instanceof Clan => $owner->parent_clan_id !== null
                ? $this->scopeOf(Clan::class, $owner->parent_clan_id)
                : $this->scopeOf(Tribe::class, $owner->tribe_id),

            $owner instanceof FamilyBranch => $owner->clan_id !== null
                ? $this->scopeOf(Clan::class, $owner->clan_id)
                : $this->scopeOf(Tribe::class, $owner->tribe_id),

            default => null,
        };
    }

    private function scopeOf(string $class, ?int $id): ?Scope
    {
        if ($id === null) {
            return null;
        }

        return Scope::where('scopeable_type', (new $class)->getMorphClass())
            ->where('scopeable_id', $id)
            ->first();
    }
}
