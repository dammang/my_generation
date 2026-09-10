<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Services\Privacy\ViewerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes a tribe's internal structure to people entitled to see it.
 *
 * Clans and family branches are not neutral metadata. A branch is a named
 * lineage with a population count and an apical ancestor, so an unscoped
 * listing hands an outsider the shape of a whole tribe — every family in it,
 * how large each is, and who each descends from — without ever touching a
 * person record. The people were protected; the skeleton was not.
 *
 * The tribe's own `default_privacy_level` decides whether outsiders may look,
 * rather than this imposing an answer on every tribe.
 */
trait BelongsToVisibleTribe
{
    public function scopeVisibleTo(Builder $query, ViewerScope $viewer): Builder
    {
        if ($viewer->isSuperAdmin) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($viewer): void {
            // A tribe that has declared itself public is browsable, which is
            // how somebody finds the family they belong to before joining.
            $query->whereHas('tribe', fn (Builder $q) => $q
                ->where('default_privacy_level', PrivacyLevel::Public));

            $query->orWhereIn('tribe_id', $viewer->tribeIds);
            $query->orWhereIn('tribe_id', $viewer->adminTribeIds);

            // A family branch also sits in a clan, so membership of that clan
            // is enough.
            if (in_array('clan_id', $this->getFillable(), true)) {
                $query->orWhereIn('clan_id', $viewer->clanIds);
                $query->orWhereIn('clan_id', $viewer->adminClanIds);
            }

            // A clan has no clan_id — it *is* the clan — so belonging to one
            // matched nothing and a member could not see the family they had
            // been approved into. Being in it is the plainest reason there is.
            if ($this instanceof Clan) {
                $query->orWhereIn('id', $viewer->clanIds);
                $query->orWhereIn('id', $viewer->adminClanIds);
            }
        });
    }

    /**
     * Route binding runs the same predicate, so a direct link is no way around
     * the listing. A record the requester may not see is answered as missing
     * rather than forbidden: a 403 confirms it exists, which on a private
     * lineage is itself the disclosure.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->visibleTo(app(ViewerScope::class))
            ->first();
    }
}
