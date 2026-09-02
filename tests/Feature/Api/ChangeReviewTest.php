<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use App\Models\ChangeRequest;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The collaboration loop: a contributor proposes, a reviewer decides, and the
 * ledger records what happened. Each half is useless without the other — a
 * proposal nobody can see is data loss with extra steps.
 */
class ChangeReviewTest extends TestCase
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

    private function memberWithRole(string $role, string $scopeType = 'tribe', ?int $scopeId = null): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', $scopeType)
            ->where('scopeable_id', $scopeId ?? $this->tribe->id)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * A reviewer whose authority is scoped to one clan and nowhere else.
     *
     * Granted through the scope_role_user table rather than a global role, so
     * the test exercises prefix-matched scope authority instead of a
     * tribe-wide permission that would pass regardless.
     */
    private function scopedReviewer(int $clanId): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'clan')
            ->where('scopeable_id', $clanId)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::where('name', 'clan-admin')->value('id'),
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        return $user;
    }

    private function verifiedPerson(): Person
    {
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Public,
            'is_living' => false,
            'first_name' => 'Thawng',
            'last_name' => 'Dam',
            'verification_status' => VerificationStatus::Verified,
        ]);
    }

    public function test_editing_a_verified_person_becomes_a_proposal(): void
    {
        // The rule the whole phase rests on: verified genealogy is never
        // silently overwritten by somebody without verify permission.
        $person = $this->verifiedPerson();

        $response = $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), [
                'first_name' => 'Thang',
                'reason' => 'The gravestone spells it Thang.',
            ])
            ->assertStatus(202);

        $this->assertNotNull($response->json('data.change_request.ulid'));
        $this->assertSame('pending', $response->json('data.change_request.status'));
        // Unchanged until somebody decides.
        $this->assertSame('Thawng', $person->fresh()->first_name);
    }

    public function test_a_contributor_can_see_their_own_proposal(): void
    {
        $person = $this->verifiedPerson();
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $response = $this->actingAs($contributor)
            ->getJson(route('api.v1.changes.index', ['filter' => 'mine']))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('First name', $response->json('data.0.diff.0.label'));
        $this->assertSame('Thawng', $response->json('data.0.diff.0.before'));
        $this->assertSame('Thang', $response->json('data.0.diff.0.after'));
    }

    public function test_a_reviewer_approves_and_the_record_changes(): void
    {
        $person = $this->verifiedPerson();
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $change = ChangeRequest::firstOrFail();
        $reviewer = $this->memberWithRole('tribe-admin');

        $this->actingAs($reviewer)
            ->postJson(route('api.v1.changes.approve', $change), ['comment' => 'Matches the stone.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('Thang', $person->fresh()->first_name);

        // The ledger records the field, not just that somebody saved.
        $this->assertDatabaseHas('revisions', [
            'revisionable_id' => $person->id,
            'field' => 'first_name',
            'change_request_id' => $change->id,
        ]);
    }

    public function test_a_rejected_proposal_leaves_the_record_alone(): void
    {
        $person = $this->verifiedPerson();

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $change = ChangeRequest::firstOrFail();

        $this->actingAs($this->memberWithRole('tribe-admin'))
            ->postJson(route('api.v1.changes.reject', $change), ['comment' => 'The register says Thawng.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('Thawng', $person->fresh()->first_name);
    }

    public function test_a_contributor_cannot_approve_their_own_proposal(): void
    {
        $person = $this->verifiedPerson();
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $this->actingAs($contributor)
            ->postJson(route('api.v1.changes.approve', ChangeRequest::firstOrFail()))
            ->assertForbidden();

        $this->assertSame('Thawng', $person->fresh()->first_name);
    }

    public function test_the_review_queue_is_empty_for_somebody_with_no_authority(): void
    {
        $person = $this->verifiedPerson();

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        // An empty allow-list must mean nothing, not everything. This is the
        // failure that turns a review queue into a leak.
        $response = $this->actingAs($this->memberWithRole('viewer'))
            ->getJson(route('api.v1.changes.index', ['filter' => 'review']))
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
        $this->assertFalse($response->json('meta.can_review'));
    }

    public function test_a_clan_reviewer_sees_proposals_from_their_own_clan(): void
    {
        // The proposal is filed against the person's scope. Without that a
        // clan reviewer's queue is empty and the feature looks broken rather
        // than misconfigured.
        $person = $this->verifiedPerson();

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $reviewer = $this->scopedReviewer($this->clan->id);

        $response = $this->actingAs($reviewer)
            ->getJson(route('api.v1.changes.index', ['filter' => 'review']))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('meta.can_review'));
    }

    public function test_a_reviewer_from_another_clan_sees_nothing(): void
    {
        $person = $this->verifiedPerson();

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $otherClan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $outsider = $this->scopedReviewer($otherClan->id);

        $response = $this->actingAs($outsider)
            ->getJson(route('api.v1.changes.index', ['filter' => 'review']))
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_a_record_that_moved_underneath_a_proposal_is_superseded(): void
    {
        $person = $this->verifiedPerson();

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        // Somebody with authority edits the same field in the meantime.
        $person->forceFill(['first_name' => 'Thawn'])->save();

        $response = $this->actingAs($this->memberWithRole('tribe-admin'))
            ->postJson(route('api.v1.changes.approve', ChangeRequest::firstOrFail()))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CHANGE_REQUEST_SUPERSEDED');

        // The reviewer needs the three-way diff to decide, not the word
        // "conflict" and a field name.
        $this->assertSame('first_name', $response->json('meta.conflicts.0.field'));
        $this->assertSame('Thawng', $response->json('meta.conflicts.0.was'));
        $this->assertSame('Thawn', $response->json('meta.conflicts.0.now'));

        $this->assertSame('Thawn', $person->fresh()->first_name);
    }

    public function test_a_requester_can_withdraw_but_not_after_a_decision(): void
    {
        $person = $this->verifiedPerson();
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        $change = ChangeRequest::firstOrFail();

        $this->actingAs($contributor)
            ->postJson(route('api.v1.changes.withdraw', $change))
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');

        $this->actingAs($contributor)
            ->postJson(route('api.v1.changes.withdraw', $change->fresh()))
            ->assertForbidden();
    }
}
