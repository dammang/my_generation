<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Dispute;
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
 * History and disagreement — the two things that make a shared archive
 * trustworthy rather than merely current.
 */
class RevisionAndDisputeTest extends TestCase
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

    private function memberWithRole(string $role): User
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

    private function person(array $overrides = []): Person
    {
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Public,
            'is_living' => false,
            ...$overrides,
        ]);
    }

    public function test_history_shows_what_a_field_used_to_say(): void
    {
        $person = $this->person(['first_name' => 'Thawng']);
        // A death date is what makes a record unambiguously historical; without
        // one the living-person mask still applies.
        $person->setUncertainDate('death', '1998')->save();

        $editor = $this->memberWithRole('tribe-admin');

        $this->actingAs($editor)
            ->patchJson(route('api.v1.people.update', $person), [
                'first_name' => 'Thang',
                'reason' => 'The gravestone spells it Thang.',
            ])
            ->assertOk();

        $response = $this->actingAs($editor)
            ->getJson(route('api.v1.people.revisions', $person))
            ->assertOk()
            ->assertJsonPath('meta.withheld', false);

        $entry = collect($response->json('data'))
            ->firstWhere('field', 'first_name');

        $this->assertNotNull($entry);
        $this->assertSame('Thawng', $entry['before']);
        $this->assertSame('Thang', $entry['after']);
        $this->assertSame('The gravestone spells it Thang.', $entry['reason']);
        $this->assertSame('First name', $entry['label']);
    }

    public function test_history_is_withheld_when_the_record_is(): void
    {
        // History is at least as revealing as the record. Reading the changes
        // to a masked person's birth year would defeat the mask entirely.
        // Born recently enough to be presumed living: a person born before the
        // max-age cutoff counts as deceased whatever is_living says, and the
        // factory's random year would make this test pass only sometimes.
        $person = $this->person([
            'is_living' => true,
            'privacy_level' => PrivacyLevel::Public,
        ]);
        $person->setUncertainDate('birth', '1980')->save();

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.revisions', $person));

        // Either unreachable, or reachable but withheld — never the contents.
        if ($response->status() === 200) {
            $this->assertTrue($response->json('meta.withheld'));
            $this->assertSame([], $response->json('data'));
        } else {
            $response->assertNotFound();
        }
    }

    public function test_a_disagreement_keeps_both_versions(): void
    {
        $person = $this->person();
        $member = $this->memberWithRole('contributor');

        $this->actingAs($member)
            ->postJson(route('api.v1.disputes.store'), [
                'person_ulid' => $person->ulid,
                'field' => 'birth_year',
                'claimed_value' => '1921',
                'rationale' => 'The baptismal register says 1921.',
            ])
            ->assertCreated();

        // A second person disagreeing about the same field joins the same
        // argument. Two disputes would split the evidence in half.
        $other = $this->memberWithRole('contributor');

        $this->actingAs($other)
            ->postJson(route('api.v1.disputes.store'), [
                'person_ulid' => $person->ulid,
                'field' => 'birth_year',
                'claimed_value' => '1923',
                'rationale' => 'My grandmother always said 1923.',
            ])
            ->assertCreated();

        $this->assertSame(1, Dispute::count());

        $response = $this->actingAs($member)
            ->getJson(route('api.v1.people.disputes', $person))
            ->assertOk();

        $values = collect($response->json('data.0.claims'))->pluck('value')->all();

        $this->assertEqualsCanonicalizing(['1921', '1923'], $values);
        $this->assertSame('open', $response->json('data.0.status'));
    }

    public function test_settling_a_disagreement_needs_authority(): void
    {
        $person = $this->person();
        $member = $this->memberWithRole('contributor');

        $this->actingAs($member)
            ->postJson(route('api.v1.disputes.store'), [
                'person_ulid' => $person->ulid,
                'field' => 'birth_year',
                'claimed_value' => '1921',
            ])
            ->assertCreated();

        $dispute = Dispute::firstOrFail();

        // Whoever complains first must not also be the one who decides.
        $this->actingAs($member)
            ->postJson(route('api.v1.disputes.resolve', $dispute), [
                'resolution' => 'claim_accepted',
                'accepted_claim_id' => $dispute->claims()->value('id'),
            ])
            ->assertForbidden();

        $this->actingAs($this->memberWithRole('tribe-admin'))
            ->postJson(route('api.v1.disputes.resolve', $dispute), [
                'resolution' => 'claim_accepted',
                'accepted_claim_id' => $dispute->claims()->value('id'),
                'note' => 'The register is the older source.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'claim_accepted');
    }

    public function test_a_settled_disagreement_is_not_settled_twice(): void
    {
        $person = $this->person();

        $this->actingAs($this->memberWithRole('contributor'))
            ->postJson(route('api.v1.disputes.store'), [
                'person_ulid' => $person->ulid,
                'field' => 'birth_year',
                'claimed_value' => '1921',
            ])
            ->assertCreated();

        $dispute = Dispute::firstOrFail();
        $admin = $this->memberWithRole('tribe-admin');

        $this->actingAs($admin)
            ->postJson(route('api.v1.disputes.resolve', $dispute), ['resolution' => 'both_recorded'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('api.v1.disputes.resolve', $dispute->fresh()), ['resolution' => 'both_recorded'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DISPUTE_DECIDED');
    }

    public function test_verifying_a_record_locks_it_against_direct_edits(): void
    {
        $person = $this->person(['first_name' => 'Thawng']);

        $this->actingAs($this->memberWithRole('tribe-admin'))
            ->postJson(route('api.v1.people.verify', $person))
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        // Verification is a gate, not a badge: from here a contributor proposes.
        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $this->assertSame('Thawng', $person->fresh()->first_name);
    }
}
