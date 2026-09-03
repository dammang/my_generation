<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Taking something out of the graph.
 *
 * The derived adjacency in `family_edges` is a cache of the truth, not the
 * truth. A deletion that removes the relationship but leaves its edge behind
 * produces a tree that draws a line to somebody no longer connected — and
 * nothing would report an error, because from the traversal's point of view
 * the graph is perfectly consistent.
 */
class GraphDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $this->branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe->id)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function person(string $firstName): Person
    {
        $person = Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Public,
            'is_living' => false,
            'first_name' => $firstName,
        ]);

        $person->setUncertainDate('death', '1990')->save();

        return $person;
    }

    /** @return array{0: Person, 1: Person, 2: Relationship} */
    private function parentAndChild(): array
    {
        $parent = $this->person('Thawng');
        $child = $this->person('Bawi');

        $this->actingAs($this->userWithRole('tribe-admin'))
            ->postJson(route('api.v1.relationships.store'), [
                'person_ulid' => $parent->ulid,
                'related_person_ulid' => $child->ulid,
            ])
            ->assertSuccessful();

        return [$parent, $child, Relationship::latest('id')->firstOrFail()];
    }

    public function test_deleting_a_relationship_retracts_its_edge(): void
    {
        [$parent, $child, $relationship] = $this->parentAndChild();

        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);

        $this->actingAs($this->userWithRole('tribe-admin'))
            ->deleteJson(route('api.v1.relationships.destroy', $relationship))
            ->assertNoContent();

        // The row survives for the audit trail; the edge must not, or the tree
        // keeps drawing a line nobody can explain.
        $this->assertDatabaseMissing('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);
        $this->assertSoftDeleted('relationships', ['id' => $relationship->id]);
    }

    public function test_deleting_a_relationship_bumps_the_graph_version(): void
    {
        [, , $relationship] = $this->parentAndChild();

        $before = DB::table('tribes')->where('id', $this->tribe->id)->value('graph_version');

        $this->actingAs($this->userWithRole('tribe-admin'))
            ->deleteJson(route('api.v1.relationships.destroy', $relationship))
            ->assertNoContent();

        $after = DB::table('tribes')->where('id', $this->tribe->id)->value('graph_version');

        // Every cached subtree key carries this. Without the bump a viewer
        // keeps being served the tree as it was before the deletion.
        $this->assertGreaterThan($before, $after);
    }

    public function test_a_viewer_cannot_delete_a_relationship(): void
    {
        [$parent, $child, $relationship] = $this->parentAndChild();

        $this->actingAs($this->userWithRole('viewer'))
            ->deleteJson(route('api.v1.relationships.destroy', $relationship))
            ->assertForbidden();

        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);
    }

    public function test_deleting_a_person_removes_them_from_the_graph(): void
    {
        [$parent, $child] = $this->parentAndChild();

        $this->actingAs($this->userWithRole('tribe-admin'))
            ->deleteJson(route('api.v1.people.destroy', $child))
            ->assertNoContent();

        $this->assertSoftDeleted('people', ['id' => $child->id]);

        // A deleted person must not still be somebody's child on the chart.
        $this->assertDatabaseMissing('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);
    }

    public function test_a_deleted_person_is_gone_from_the_api(): void
    {
        [, $child] = $this->parentAndChild();

        $admin = $this->userWithRole('tribe-admin');

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.people.destroy', $child))
            ->assertNoContent();

        $this->actingAs($admin)
            ->getJson(route('api.v1.people.show', $child))
            ->assertNotFound();
    }

    public function test_restoring_a_person_brings_their_edges_back(): void
    {
        [$parent, $child] = $this->parentAndChild();
        $admin = $this->userWithRole('tribe-admin');

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.people.destroy', $child))
            ->assertNoContent();

        $child->restore();

        // Re-derived from the relationships that survived, not replayed from a
        // record of what was removed — a relationship deleted while the person
        // was gone must not come back with them.
        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);
    }

    public function test_a_restored_person_does_not_regain_a_deleted_link(): void
    {
        [$parent, $child, $relationship] = $this->parentAndChild();
        $admin = $this->userWithRole('tribe-admin');

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.people.destroy', $child))
            ->assertNoContent();

        $relationship->delete();
        $child->restore();

        $this->assertDatabaseMissing('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
        ]);
    }

    public function test_a_contributor_cannot_delete_a_person(): void
    {
        [, $child] = $this->parentAndChild();

        $this->actingAs($this->userWithRole('contributor'))
            ->deleteJson(route('api.v1.people.destroy', $child))
            ->assertForbidden();

        $this->assertNotSoftDeleted('people', ['id' => $child->id]);
    }
}
