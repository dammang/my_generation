<?php

declare(strict_types=1);

namespace Tests\Unit\Privacy;

use App\Services\Privacy\ViewerScope;
use PHPUnit\Framework\TestCase;

/**
 * ViewerScope is cached as primitives, not as a serialized object.
 *
 * This is a regression test for a bug the rest of the suite could not catch:
 * tests run on the array cache driver, which stores objects by reference and
 * never serialises. On a real driver, a cache entry written before a deploy
 * that changed the class came back as __PHP_Incomplete_Class, and every
 * request 500'd until somebody cleared the cache by hand.
 */
class ViewerScopeCacheTest extends TestCase
{
    private function scope(): ViewerScope
    {
        return new ViewerScope(
            userId: 7,
            personId: 42,
            tribeIds: [1, 2],
            clanIds: [3],
            branchIds: [4],
            adminScopePaths: ['/1/', '/1/3/'],
            adminTribeIds: [1],
            adminClanIds: [3],
            adminBranchIds: [4],
            kinPersonIds: [42, 43, 44],
            permissions: ['people.view', 'people.create'],
            isSuperAdmin: false,
        );
    }

    public function test_it_round_trips_through_primitives(): void
    {
        $restored = ViewerScope::fromArray($this->scope()->toArray());

        $this->assertEquals($this->scope(), $restored);
    }

    public function test_the_cached_payload_contains_no_objects(): void
    {
        $payload = $this->scope()->toArray();

        array_walk_recursive($payload, function ($value): void {
            $this->assertFalse(is_object($value), 'Cached payload must be primitives only');
        });
    }

    public function test_a_payload_from_an_older_shape_is_discarded(): void
    {
        // A deploy that changes the class bumps CACHE_VERSION, and stale
        // entries are rebuilt rather than rehydrated into the wrong shape.
        $stale = $this->scope()->toArray();
        $stale['v'] = ViewerScope::CACHE_VERSION - 1;

        $this->assertNull(ViewerScope::fromArray($stale));
    }

    public function test_a_payload_with_no_version_is_discarded(): void
    {
        $payload = $this->scope()->toArray();
        unset($payload['v']);

        $this->assertNull(ViewerScope::fromArray($payload));
    }

    public function test_the_hash_survives_the_round_trip(): void
    {
        // The hash keys every cached tree and person payload. If it changed
        // across a cache round trip, caches would silently miss forever.
        $restored = ViewerScope::fromArray($this->scope()->toArray());

        $this->assertSame($this->scope()->hash(), $restored->hash());
    }

    public function test_different_entitlements_produce_different_hashes(): void
    {
        $a = $this->scope();
        $b = new ViewerScope(userId: 7, personId: 42, tribeIds: [1, 2, 99]);

        $this->assertNotSame($a->hash(), $b->hash());
    }

    public function test_a_guest_scope_reaches_nothing(): void
    {
        $guest = ViewerScope::guest();

        $this->assertTrue($guest->isGuest());
        $this->assertFalse($guest->administers('/1/'));
        $this->assertFalse($guest->belongsToTribe(1));
        $this->assertFalse($guest->isKin(42));
        $this->assertFalse($guest->administersPlacement(1, 3, 4));
    }

    public function test_administering_a_parent_scope_carries_the_children(): void
    {
        $scope = new ViewerScope(userId: 7, adminScopePaths: ['/1/']);

        $this->assertTrue($scope->administers('/1/'));
        $this->assertTrue($scope->administers('/1/14/'));
        $this->assertTrue($scope->administers('/1/14/57/'));
        $this->assertFalse($scope->administers('/2/'));
        $this->assertFalse($scope->administers(null));
    }
}
