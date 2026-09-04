<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Story;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may read a family's stories.
 *
 * StoryPolicy::view() answered true for everybody until these routes existed.
 * That was harmless while nothing could ask and a disclosure the moment
 * something could, so the rule is pinned here rather than trusted.
 */
class StoryTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create(['default_privacy_level' => PrivacyLevel::Tribe]);
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $this->branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
        ]);
    }

    private function story(PrivacyLevel $visibility, ?User $author = null): Story
    {
        return Story::factory()->create([
            'visibility' => $visibility,
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'author_id' => $author?->getKey(),
        ]);
    }

    private function outsider(): User
    {
        $user = User::factory()->create();
        $user->assignRole('contributor');

        return $user;
    }

    private function tribeMember(string $role = 'contributor'): User
    {
        $user = User::factory()->create();
        $scopeId = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe->id)
            ->firstOrFail()->id;

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scopeId,
            'status' => MembershipStatus::Active,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * An administrator *of this tribe*, which is not the same as somebody
     * holding the tribe-admin role globally: ViewerScope fills adminTribeIds
     * from scope_role_user, so a global grant leaves it empty and the viewer
     * administers nothing in particular.
     */
    private function tribeAdmin(): User
    {
        $user = $this->tribeMember('tribe-admin');

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::where('name', 'tribe-admin')->where('guard_name', 'web')->value('id'),
            'scope_id' => Scope::where('scopeable_type', 'tribe')
                ->where('scopeable_id', $this->tribe->id)
                ->firstOrFail()->id,
            'granted_at' => now(),
        ]);

        return $user;
    }

    private function listedUlids(User $user): array
    {
        $response = $this->actingAs($user)->getJson(route('api.v1.stories.index'));
        $response->assertOk();

        return collect($response->json('data'))->pluck('ulid')->all();
    }

    public function test_a_public_story_is_readable_by_somebody_outside_the_tribe(): void
    {
        $story = $this->story(PrivacyLevel::Public);

        $this->assertContains($story->ulid, $this->listedUlids($this->outsider()));
    }

    public function test_a_tribe_story_is_not_readable_by_an_outsider(): void
    {
        $story = $this->story(PrivacyLevel::Tribe);

        $this->assertNotContains($story->ulid, $this->listedUlids($this->outsider()));
    }

    public function test_a_tribe_story_is_readable_by_a_member(): void
    {
        $story = $this->story(PrivacyLevel::Tribe);

        $this->assertContains($story->ulid, $this->listedUlids($this->tribeMember()));
    }

    public function test_a_family_story_is_not_readable_by_a_tribe_member_outside_that_branch(): void
    {
        $story = $this->story(PrivacyLevel::Family);

        // Belonging to the tribe is not belonging to the family. This is the
        // case a single "are they in the tribe?" check would get wrong.
        $this->assertNotContains($story->ulid, $this->listedUlids($this->tribeMember()));
    }

    public function test_a_family_story_is_readable_by_an_administrator_of_the_tribe_above_it(): void
    {
        $story = $this->story(PrivacyLevel::Family);

        // Authority flows downward: a tribe admin can already read the family.
        $this->assertContains($story->ulid, $this->listedUlids($this->tribeAdmin()));
    }

    public function test_a_private_story_is_readable_only_by_its_author(): void
    {
        $author = $this->tribeMember();
        $story = $this->story(PrivacyLevel::Private, $author);

        $this->assertContains($story->ulid, $this->listedUlids($author));
        $this->assertNotContains($story->ulid, $this->listedUlids($this->tribeMember()));
    }

    public function test_fetching_a_story_directly_obeys_the_same_rule_as_the_listing(): void
    {
        $story = $this->story(PrivacyLevel::Tribe);

        // A policy that disagreed with the listing would make the id itself
        // the way around it.
        $this->actingAs($this->outsider())
            ->getJson(route('api.v1.stories.show', $story))
            ->assertForbidden();

        $this->actingAs($this->tribeMember())
            ->getJson(route('api.v1.stories.show', $story))
            ->assertOk()
            ->assertJsonPath('data.ulid', $story->ulid);
    }

    public function test_a_listing_carries_the_summary_and_not_the_whole_body(): void
    {
        // Three, not one. Collection::mapInto() constructs each resource as
        // `new static($item, $key)`, so anything reading a second constructor
        // argument gets the collection index — and index 0 is the one value
        // that behaves. A single-story listing passed this while every story
        // after the first was sending its whole body.
        $this->story(PrivacyLevel::Public);
        $this->story(PrivacyLevel::Public);
        $this->story(PrivacyLevel::Public);

        $response = $this->actingAs($this->outsider())
            ->getJson(route('api.v1.stories.index'))
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(3, $rows);

        foreach ($rows as $index => $row) {
            $this->assertArrayHasKey('summary', $row, "row {$index} lost its summary");
            $this->assertArrayNotHasKey('body', $row, "row {$index} leaked its body");
        }
    }

    public function test_asking_for_one_story_does_return_its_body(): void
    {
        $story = $this->story(PrivacyLevel::Public);

        $this->actingAs($this->outsider())
            ->getJson(route('api.v1.stories.show', $story))
            ->assertOk()
            ->assertJsonPath('data.body', $story->body);
    }

    public function test_writing_a_story_is_recorded_against_its_author(): void
    {
        $author = $this->tribeMember();

        $this->actingAs($author)
            ->postJson(route('api.v1.stories.store'), [
                'title' => 'The weather diaries',
                'body' => 'He kept a note of the weather every day for forty years.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'The weather diaries');

        $this->assertDatabaseHas('stories', [
            'title' => 'The weather diaries',
            'author_id' => $author->id,
        ]);
    }

    public function test_a_new_story_defaults_to_family_rather_than_public(): void
    {
        $this->actingAs($this->tribeMember())
            ->postJson(route('api.v1.stories.store'), [
                'title' => 'Something about living people',
                'body' => 'The safe default for this is not the one that publishes it.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', PrivacyLevel::Family->value);
    }
}
