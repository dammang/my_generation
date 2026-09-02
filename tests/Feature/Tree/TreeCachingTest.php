<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TreeCachingTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->tribe = Tribe::factory()->create();
    }

    private function person(int $birth, PrivacyLevel $level = PrivacyLevel::Public): Person
    {
        return Person::factory()->bornExactly($birth)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => $level,
        ]);
    }

    public function test_an_unchanged_tree_returns_304_for_a_matching_etag(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $person = $this->person(1950);

        $first = $this->actingAs($admin)
            ->getJson(route('api.v1.tree.show', $person))
            ->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->actingAs($admin)
            ->withHeader('If-None-Match', $etag)
            ->getJson(route('api.v1.tree.show', $person))
            ->assertStatus(304);
    }

    public function test_a_genealogy_write_invalidates_the_cached_tree(): void
    {
        // graph_version is in the cache key, so one integer bump retires every
        // cached subtree in the tribe at once.
        $admin = User::factory()->create(['is_super_admin' => true]);
        $person = $this->person(1950);

        $before = $this->actingAs($admin)->getJson(route('api.v1.tree.show', $person))->assertOk();
        $this->assertCount(1, $before->json('data.people'));

        $child = $this->person(1980);
        Relationship::factory()->parentChild($person, $child)->create();

        $after = $this->actingAs($admin)->getJson(route('api.v1.tree.show', $person))->assertOk();

        $this->assertCount(2, $after->json('data.people'), 'A stale tree would still show one person');
        $this->assertGreaterThan(
            $before->json('meta.graph_version'),
            $after->json('meta.graph_version'),
        );
    }

    public function test_two_viewers_with_different_entitlements_do_not_share_a_cache_entry(): void
    {
        // The single most important property of the cache key. A shared entry
        // across a permission boundary is a silent privacy breach.
        $person = $this->person(1950, PrivacyLevel::Tribe);
        $person->setUncertainDate('death', '2001')->save();

        $insider = User::factory()->create();
        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();
        Membership::create([
            'user_id' => $insider->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        $outsider = User::factory()->create();

        // The insider primes the cache first.
        $this->actingAs($insider)
            ->getJson(route('api.v1.tree.show', $person))
            ->assertOk()
            ->assertJsonPath('data.people.0.display_name', $person->display_name);

        // The outsider cannot even reach the focus person.
        $this->actingAs($outsider)
            ->getJson(route('api.v1.tree.show', $person))
            ->assertNotFound();
    }

    public function test_a_living_relative_is_masked_inside_the_tree(): void
    {
        // The mask applies at every depth, not only at the root.
        $insider = User::factory()->create();
        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();
        Membership::create([
            'user_id' => $insider->id, 'scope_id' => $scope->id, 'status' => MembershipStatus::Active,
        ]);

        $ancestor = $this->person(1900, PrivacyLevel::Tribe);
        $ancestor->setUncertainDate('death', '1970')->save();

        $living = $this->person(1985, PrivacyLevel::Tribe);
        Relationship::factory()->parentChild($ancestor, $living)->create();

        $response = $this->actingAs($insider)
            ->getJson(route('api.v1.tree.show', ['person' => $ancestor, 'descendants' => 1]))
            ->assertOk();

        $nodes = collect($response->json('data.people'))->keyBy('ulid');

        $this->assertFalse($nodes[$ancestor->ulid]['redacted'], 'A deceased ancestor is shown in full');
        $this->assertTrue($nodes[$living->ulid]['redacted'], 'A living descendant is masked');
        $this->assertNull($nodes[$living->ulid]['birth']);

        // The shape of the graph survives even when the content does not —
        // hiding the node would misrepresent everyone else's lineage.
        $this->assertCount(1, $response->json('data.edges'));
    }

    public function test_the_cache_is_never_load_bearing_for_correctness(): void
    {
        // A cold cache must produce identical results, only slower.
        $admin = User::factory()->create(['is_super_admin' => true]);
        $parent = $this->person(1920);
        $child = $this->person(1950);
        Relationship::factory()->parentChild($parent, $child)->create();

        $warm = $this->actingAs($admin)->getJson(route('api.v1.tree.show', $parent))->assertOk();

        Cache::flush();

        $cold = $this->actingAs($admin)->getJson(route('api.v1.tree.show', $parent))->assertOk();

        $this->assertSame($warm->json('data'), $cold->json('data'));
    }
}
