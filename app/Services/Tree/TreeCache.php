<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Models\Person;
use App\Services\Privacy\ViewerScope;
use Illuminate\Support\Facades\Cache;

/**
 * Caching for tree responses.
 *
 * Two things are in every key, and both are load-bearing:
 *
 *   graph_version — one integer per tribe, bumped on any genealogy write there.
 *   Invalidation becomes O(1) instead of discovering which of thousands of
 *   cached subgraphs contained the changed person, which is not knowable
 *   without walking the graph — the expensive thing the cache exists to avoid.
 *
 *   the viewer's scope hash — so a cached payload can never be served across a
 *   permission boundary. Two viewers share a cache entry only when their
 *   entitlements are identical.
 *
 * Caching is never load-bearing for correctness: a cold cache must produce
 * identical results, only slower.
 */
class TreeCache
{
    public function key(
        Person $focus,
        ViewerScope $viewer,
        int $ancestors,
        int $descendants,
        int $budget,
        int $graphVersion,
    ): string {
        return implode(':', [
            'tree',
            $focus->ulid,
            $ancestors,
            $descendants,
            $budget,
            $viewer->hash(),
            $graphVersion,
        ]);
    }

    /**
     * @param  \Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function remember(string $key, \Closure $build): array
    {
        return Cache::remember($key, (int) config('genealogy.tree.cache_ttl'), $build);
    }

    /**
     * A stable fingerprint of the payload, for conditional requests. A phone
     * revisiting an unchanged tree gets a 304 and transfers nothing.
     */
    public function etag(array $payload): string
    {
        return '"'.substr(hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 24).'"';
    }
}
