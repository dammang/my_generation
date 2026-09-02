<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API decides what a requester may see. These tests exist because the
 * failure mode is silent: a leak looks exactly like a working endpoint.
 */
class PersonPrivacyTest extends TestCase
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

    private function member(string $type, int $id): User
    {
        $user = User::factory()->create();
        $scope = Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        return $user;
    }

    private function personIn(PrivacyLevel $level, array $overrides = []): Person
    {
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => $level,
            ...$overrides,
        ]);
    }

    public function test_a_public_deceased_person_is_visible_to_any_member(): void
    {
        $person = $this->personIn(PrivacyLevel::Public);
        $person->setUncertainDate('death', '1961')->save();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.display_name', $person->display_name);
    }

    public function test_a_tribe_scoped_person_returns_404_to_an_outsider(): void
    {
        // 404 rather than 403: a 403 confirms the record exists, which on a
        // restricted person is itself the disclosure.
        $person = $this->personIn(PrivacyLevel::Tribe);

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_a_tribe_scoped_person_is_visible_to_a_tribe_member(): void
    {
        $person = $this->personIn(PrivacyLevel::Tribe);
        $person->setUncertainDate('death', '1961')->save();

        $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();
    }

    public function test_a_tribe_member_cannot_see_a_family_scoped_person(): void
    {
        $person = $this->personIn(PrivacyLevel::Family);

        $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    public function test_a_branch_member_can_see_a_family_scoped_person(): void
    {
        $person = $this->personIn(PrivacyLevel::Family);

        $this->actingAs($this->member('family_branch', $this->branch->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();
    }

    public function test_a_private_person_is_hidden_even_from_branch_members(): void
    {
        $person = $this->personIn(PrivacyLevel::Private);

        $this->actingAs($this->member('family_branch', $this->branch->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    public function test_a_contributor_keeps_sight_of_what_they_added(): void
    {
        $contributor = User::factory()->create();
        $person = $this->personIn(PrivacyLevel::Private, ['created_by' => $contributor->id]);

        $this->actingAs($contributor)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();
    }

    public function test_a_living_persons_dates_are_withheld_from_distant_viewers(): void
    {
        // Born recently enough to be genuinely living. The factory's default
        // range reaches back past the 110-year cutoff, at which point the
        // person is correctly inferred deceased and the dates are public.
        $person = $this->personIn(PrivacyLevel::Tribe);
        $person->setUncertainDate('birth', '1980')->save();

        $response = $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();

        $this->assertNull($response->json('data.birth'), 'A living person’s dates must not reach a distant viewer');
        $this->assertNull($response->json('data.biography'));
        $this->assertTrue($response->json('data.redacted'));
        // The name and position survive, or the tree would misrepresent lineage.
        $this->assertSame($person->display_name, $response->json('data.display_name'));
    }

    public function test_a_deceased_persons_dates_are_shown(): void
    {
        $person = $this->personIn(PrivacyLevel::Tribe);
        $person->setUncertainDate('birth', '1898')->setUncertainDate('death', '1961')->save();

        $response = $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();

        $this->assertSame(1898, $response->json('data.birth.year'));
        $this->assertFalse($response->json('data.redacted'));
    }

    public function test_someone_born_beyond_the_maximum_age_is_treated_as_deceased(): void
    {
        // No death record, but nobody born in 1850 is living. Inferring this
        // is what stops a nineteenth-century ancestor being locked away as if
        // they had privacy interests.
        $person = $this->personIn(PrivacyLevel::Tribe);
        $person->setUncertainDate('birth', '1850')->save();

        $response = $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();

        $this->assertSame(1850, $response->json('data.birth.year'));
        $this->assertFalse($response->json('data.redacted'));
    }

    public function test_a_living_minor_is_hidden_from_the_tribe(): void
    {
        $person = $this->personIn(PrivacyLevel::Public);
        $person->setUncertainDate('birth', (string) ((int) date('Y') - 8))->save();

        $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    public function test_a_minor_is_visible_to_their_family_branch(): void
    {
        $person = $this->personIn(PrivacyLevel::Family);
        $person->setUncertainDate('birth', (string) ((int) date('Y') - 8))->save();

        $this->actingAs($this->member('family_branch', $this->branch->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();
    }

    public function test_a_person_with_no_dates_is_treated_as_living(): void
    {
        // Fail closed: the alternative is publishing a living person's details
        // on the strength of missing data.
        $person = $this->personIn(PrivacyLevel::Tribe);
        $person->forceFill(['birth_date' => null, 'birth_year' => null])->save();

        $response = $this->actingAs($this->member('tribe', $this->tribe->id))
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();

        $this->assertTrue($response->json('data.redacted'));
    }

    public function test_the_index_never_lists_a_person_the_viewer_cannot_see(): void
    {
        $visible = $this->personIn(PrivacyLevel::Public, ['is_living' => false]);
        $visible->setUncertainDate('death', '1961')->save();
        $this->personIn(PrivacyLevel::Family);
        $this->personIn(PrivacyLevel::Private);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.index'))
            ->assertOk();

        $ulids = collect($response->json('data'))->pluck('ulid');

        $this->assertSame([$visible->ulid], $ulids->all());
    }

    public function test_the_index_filters_in_sql_rather_than_after_pagination(): void
    {
        // Post-filtering produces short pages and leaks total counts. With 20
        // hidden records and 3 visible, a per_page of 3 must still return 3.
        Person::factory()->count(20)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Private,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $person = $this->personIn(PrivacyLevel::Public);
            $person->setUncertainDate('death', '1950')->save();
        }

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.index', ['per_page' => 3]))
            ->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_a_super_admin_sees_everything(): void
    {
        $person = $this->personIn(PrivacyLevel::Private);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.redacted', false);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $person = $this->personIn(PrivacyLevel::Public);

        $this->getJson(route('api.v1.people.show', $person))->assertUnauthorized();
    }
}
