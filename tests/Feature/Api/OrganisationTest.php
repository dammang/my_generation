<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Place;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function grant(User $user, string $role, string $type, int $id): void
    {
        $scope = Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->firstOrFail();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName($role, 'web')->id,
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        app(PermissionResolver::class)->forget($user);
        app(ViewerScopeResolver::class)->forget($user);
    }

    // ── Tribes ───────────────────────────────────────────────────────────

    public function test_creating_a_tribe_returns_201_and_derives_a_slug(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('api.v1.tribes.store'), ['name' => 'Zomi', 'country_code' => 'mm'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'zomi')
            ->assertJsonPath('data.country_code', 'MM');
    }

    public function test_a_contributor_cannot_create_a_tribe(): void
    {
        $user = User::factory()->create();
        $user->assignRole('contributor');

        $this->actingAs($user)
            ->postJson(route('api.v1.tribes.store'), ['name' => 'Zomi'])
            ->assertForbidden();
    }

    public function test_creating_a_tribe_creates_its_scope_row(): void
    {
        // Without this a tribe would exist with nothing to hang permissions on.
        $this->actingAs($this->admin())
            ->postJson(route('api.v1.tribes.store'), ['name' => 'Zomi'])
            ->assertCreated();

        $tribe = Tribe::where('slug', 'zomi')->firstOrFail();

        $this->assertDatabaseHas('scopes', [
            'scopeable_type' => 'tribe',
            'scopeable_id' => $tribe->id,
            'path' => '/'.Scope::where('scopeable_id', $tribe->id)->value('id').'/',
        ]);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Tribe::factory()->create(['slug' => 'zomi']);

        $this->actingAs($this->admin())
            ->postJson(route('api.v1.tribes.store'), ['name' => 'Zomi', 'slug' => 'zomi'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['slug']]);
    }

    public function test_a_tribe_with_clans_returns_409_rather_than_a_foreign_key_error(): void
    {
        $tribe = Tribe::factory()->create();
        Clan::factory()->create(['tribe_id' => $tribe->id]);

        $this->actingAs($this->admin())
            ->deleteJson(route('api.v1.tribes.destroy', $tribe))
            ->assertStatus(409)
            ->assertJsonPath('code', 'SCOPE_NOT_EMPTY');
    }

    public function test_an_empty_tribe_can_be_deleted(): void
    {
        $tribe = Tribe::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson(route('api.v1.tribes.destroy', $tribe))
            ->assertNoContent();

        $this->assertSoftDeleted('tribes', ['id' => $tribe->id]);
    }

    public function test_tribe_statistics_report_real_counts(): void
    {
        $tribe = Tribe::factory()->create();
        Clan::factory()->count(2)->create(['tribe_id' => $tribe->id]);
        // bornExactly, so these are unambiguously living: the factory's default
        // range reaches past the 110-year cutoff and would infer some deceased.
        Person::factory()->count(4)->bornExactly(1980)->create(['tribe_id' => $tribe->id]);
        Person::factory()->bornExactly(1890)->deceased(1961)->create(['tribe_id' => $tribe->id]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('api.v1.tribes.statistics', $tribe))
            ->assertOk();

        $this->assertSame(5, $response->json('data.people.total'));
        $this->assertSame(1, $response->json('data.people.deceased'));
        $this->assertSame(2, $response->json('data.structure.clans'));
    }

    // ── Clan hierarchy of arbitrary depth ────────────────────────────────

    public function test_clans_nest_to_arbitrary_depth(): void
    {
        // Nothing assumes three levels, or any number.
        $tribe = Tribe::factory()->create();
        $admin = $this->admin();

        $parentUlid = null;
        $labels = ['Clan', 'Sub-clan', 'Branch', 'House', 'Line'];

        foreach ($labels as $depth => $label) {
            $response = $this->actingAs($admin)
                ->postJson(route('api.v1.clans.store'), array_filter([
                    'tribe_ulid' => $tribe->ulid,
                    'parent_clan_ulid' => $parentUlid,
                    'name' => "Level {$depth}",
                    'slug' => "level-{$depth}",
                    'level_label' => $label,
                ]))
                ->assertCreated()
                ->assertJsonPath('data.depth', $depth)
                ->assertJsonPath('data.level_label', $label);

            $parentUlid = $response->json('data.ulid');
        }

        $deepest = Clan::where('slug', 'level-4')->firstOrFail();
        $this->assertSame(4, $deepest->depth);
        $this->assertSame(5, substr_count($deepest->path, '/') - 1);
    }

    public function test_a_clan_cannot_be_parented_to_another_tribe(): void
    {
        $mine = Tribe::factory()->create();
        $theirs = Tribe::factory()->create();
        $foreign = Clan::factory()->create(['tribe_id' => $theirs->id]);

        $this->actingAs($this->admin())
            ->postJson(route('api.v1.clans.store'), [
                'tribe_ulid' => $mine->ulid,
                'parent_clan_ulid' => $foreign->ulid,
                'name' => 'Orphan',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['parent_clan_ulid']]);
    }

    public function test_a_clan_cannot_be_moved_beneath_its_own_descendant(): void
    {
        // Otherwise the subtree detaches from the tribe entirely and every
        // permission check below the move answers with a broken hierarchy.
        $tribe = Tribe::factory()->create();
        $parent = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $child = Clan::factory()->under($parent)->create();

        $this->actingAs($this->admin())
            ->patchJson(route('api.v1.clans.update', $parent), ['parent_clan_ulid' => $child->ulid])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['parent_clan_ulid']]);
    }

    public function test_a_clan_cannot_be_its_own_parent(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $this->actingAs($this->admin())
            ->patchJson(route('api.v1.clans.update', $clan), ['parent_clan_ulid' => $clan->ulid])
            ->assertStatus(422);
    }

    public function test_re_parenting_a_clan_repaths_everything_beneath_it(): void
    {
        $tribe = Tribe::factory()->create();
        $first = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $second = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $moving = Clan::factory()->under($first)->create();
        $grandchild = Clan::factory()->under($moving)->create();

        $this->actingAs($this->admin())
            ->patchJson(route('api.v1.clans.update', $moving), ['parent_clan_ulid' => $second->ulid])
            ->assertOk();

        $this->assertStringStartsWith($second->fresh()->path, $moving->fresh()->path);
        $this->assertStringStartsWith($moving->fresh()->path, $grandchild->fresh()->path);

        // The scope spine has to move with it, or authority checks below the
        // clan keep answering with the old hierarchy.
        $movedScope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $moving->id)->firstOrFail();
        $secondScope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $second->id)->firstOrFail();
        $grandchildScope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $grandchild->id)->firstOrFail();

        $this->assertStringStartsWith($secondScope->path, $movedScope->path);
        $this->assertStringStartsWith($movedScope->path, $grandchildScope->path);
    }

    public function test_listing_clans_walks_one_level_at_a_time(): void
    {
        $tribe = Tribe::factory()->create();
        $root = Clan::factory()->create(['tribe_id' => $tribe->id]);
        Clan::factory()->count(3)->under($root)->create();

        $roots = $this->actingAs($this->admin())
            ->getJson(route('api.v1.tribes.clans', $tribe))
            ->assertOk();

        $this->assertCount(1, $roots->json('data'));
        $this->assertTrue($roots->json('data.0.has_children'));

        $children = $this->actingAs($this->admin())
            ->getJson(route('api.v1.tribes.clans', ['tribe' => $tribe->ulid, 'parent' => $root->ulid]))
            ->assertOk();

        $this->assertCount(3, $children->json('data'));
    }

    // ── Family branches ──────────────────────────────────────────────────

    public function test_a_branch_can_name_its_apical_ancestor(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $ancestor = Person::factory()->deceased(1961)->create([
            'tribe_id' => $tribe->id,
            'privacy_level' => PrivacyLevel::Public,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('api.v1.branches.store'), [
                'tribe_ulid' => $tribe->ulid,
                'clan_ulid' => $clan->ulid,
                'ancestor_person_ulid' => $ancestor->ulid,
                'name' => 'Kin Tun Family',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'kin-tun-family');

        $this->assertSame(
            $ancestor->id,
            FamilyBranch::where('slug', 'kin-tun-family')->value('ancestor_person_id')
        );
    }

    public function test_a_branch_gets_a_scope_beneath_its_clan(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $branch = FamilyBranch::factory()->create(['tribe_id' => $tribe->id, 'clan_id' => $clan->id]);

        $clanScope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $clan->id)->firstOrFail();
        $branchScope = Scope::where('scopeable_type', 'family_branch')->where('scopeable_id', $branch->id)->firstOrFail();

        $this->assertStringStartsWith($clanScope->path, $branchScope->path);
    }

    // ── Places ───────────────────────────────────────────────────────────

    public function test_a_place_hierarchy_builds_its_path(): void
    {
        $user = User::factory()->create();
        $user->assignRole('contributor');

        $country = $this->actingAs($user)
            ->postJson(route('api.v1.places.store'), ['name' => 'Myanmar', 'type' => 'country', 'country_code' => 'mm'])
            ->assertCreated()->json('data.ulid');

        $state = $this->actingAs($user)
            ->postJson(route('api.v1.places.store'), ['name' => 'Chin State', 'type' => 'state', 'parent_ulid' => $country])
            ->assertCreated()->json('data.ulid');

        $village = $this->actingAs($user)
            ->postJson(route('api.v1.places.store'), ['name' => 'Khuasak', 'type' => 'village', 'parent_ulid' => $state])
            ->assertCreated()->json('data.ulid');

        $this->actingAs($user)
            ->getJson(route('api.v1.places.show', $village))
            ->assertOk()
            ->assertJsonPath('meta.full_name', 'Khuasak, Chin State, Myanmar')
            ->assertJsonPath('meta.depth', 2);
    }

    public function test_the_place_index_returns_roots_by_default(): void
    {
        $user = User::factory()->create();
        $country = Place::factory()->ofType('country')->create(['name' => 'Myanmar']);
        Place::factory()->create(['name' => 'Khuasak', 'parent_id' => $country->id]);

        $response = $this->actingAs($user)->getJson(route('api.v1.places.index'))->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Myanmar', $response->json('data.0.name'));
    }
}
