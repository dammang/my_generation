<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Tribe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate counts for a tribe page.
 *
 * Cached on a plain time window, deliberately not on graph_version.
 *
 * Versioning is right for a cached subtree, where stale means wrong. It is the
 * wrong instinct here: every genealogy write bumps the version, so an active
 * tribe recomputed a dozen full-table counts on the very next request —
 * measured at 417ms over 101,000 people, and linear from there. A dashboard
 * number an hour behind is still a dashboard number; one that costs four
 * seconds is a problem.
 *
 * Built from indexed columns and denormalised counters rather than by walking
 * the graph: statistics must never be the reason a page is slow.
 */
class ScopeStatistics
{
    /** An hour. Long enough to absorb a burst of edits, short enough to feel live. */
    private const TTL = 3600;

    /** @return array<string, mixed> */
    public function forTribe(Tribe $tribe): array
    {
        return Cache::remember(
            "stats:tribe:{$tribe->getKey()}",
            self::TTL,
            fn () => $this->build($tribe),
        );
    }

    /** @return array<string, mixed> */
    private function build(Tribe $tribe): array
    {
        $people = DB::table('people')
            ->where('tribe_id', $tribe->getKey())
            ->whereNull('deleted_at')
            ->whereNull('merged_into_person_id');

        $living = (clone $people)->where('is_living', true)->count();
        $total = (clone $people)->count();

        $earliest = (clone $people)->whereNotNull('birth_year')->min('birth_year');
        $latest = (clone $people)->whereNotNull('birth_year')->max('birth_year');

        return [
            'people' => [
                'total' => $total,
                'living' => $living,
                'deceased' => $total - $living,
                'verified' => (clone $people)->where('verification_status', 'verified')->count(),
            ],
            'structure' => [
                'clans' => DB::table('clans')->where('tribe_id', $tribe->getKey())->whereNull('deleted_at')->count(),
                'family_branches' => DB::table('family_branches')->where('tribe_id', $tribe->getKey())->whereNull('deleted_at')->count(),
                'generations' => DB::table('generations')->where('tribe_id', $tribe->getKey())->count(),
            ],
            'relationships' => [
                'edges' => DB::table('family_edges')->where('tribe_id', $tribe->getKey())->count(),
                'unions' => DB::table('unions')
                    ->join('people', 'people.id', '=', 'unions.partner_1_id')
                    ->where('people.tribe_id', $tribe->getKey())
                    ->whereNull('unions.deleted_at')
                    ->count(),
            ],
            'span' => [
                'earliest_birth_year' => $earliest === null ? null : (int) $earliest,
                'latest_birth_year' => $latest === null ? null : (int) $latest,
            ],
            'places' => [
                'countries' => DB::table('people')
                    ->join('places', 'places.id', '=', 'people.birth_place_id')
                    ->where('people.tribe_id', $tribe->getKey())
                    ->whereNull('people.deleted_at')
                    ->distinct()
                    ->count('places.country_code'),
            ],
            'graph_version' => $tribe->graph_version,
        ];
    }
}
