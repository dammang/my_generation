<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ClaimStatus;
use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Membership;
use App\Models\Person;
use App\Models\ProfileClaim;
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

/**
 * Claiming links an account to a genealogy record — and, because "family" is a
 * relational scope, widens what the claimant can see across a whole family. The
 * guardrails matter more than the happy path.
 */
class ProfileClaimTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->tribe = Tribe::factory()->create();
    }

    private function member(): User
    {
        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        return $user;
    }

    private function familyAdmin(): User
    {
        $user = User::factory()->create();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName('family-admin', 'web')->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'granted_at' => now(),
        ]);

        app(PermissionResolver::class)->forget($user);
        app(ViewerScopeResolver::class)->forget($user);

        return $user;
    }

    private function livingPerson(): Person
    {
        return Person::factory()->bornExactly(1985)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Tribe,
        ]);
    }

    public function test_a_member_can_claim_a_living_person(): void
    {
        $person = $this->livingPerson();

        $this->actingAs($this->member())
            ->postJson(route('api.v1.claims.store'), [
                'person_ulid' => $person->ulid,
                'relationship_statement' => 'This is me. My father is Hau Neng.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ClaimStatus::Pending->value);
    }

    public function test_a_claim_never_links_the_account_by_itself(): void
    {
        // Pending means pending: submitting must grant nothing.
        $user = $this->member();
        $person = $this->livingPerson();

        $this->actingAs($user)->postJson(route('api.v1.claims.store'), [
            'person_ulid' => $person->ulid,
        ])->assertCreated();

        $this->assertNull($user->fresh()->person_id);
    }

    public function test_a_deceased_person_cannot_be_claimed(): void
    {
        $person = Person::factory()->bornExactly(1890)->deceased(1961)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Tribe,
        ]);

        $this->actingAs($this->member())
            ->postJson(route('api.v1.claims.store'), ['person_ulid' => $person->ulid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PERSON_DECEASED');
    }

    public function test_a_person_someone_is_already_verified_as_cannot_be_claimed(): void
    {
        $person = $this->livingPerson();
        User::factory()->create()->forceFill(['person_id' => $person->id])->save();

        $this->actingAs($this->member())
            ->postJson(route('api.v1.claims.store'), ['person_ulid' => $person->ulid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PERSON_ALREADY_CLAIMED');
    }

    public function test_an_account_already_linked_cannot_claim_another_person(): void
    {
        $user = $this->member();
        $user->forceFill(['person_id' => $this->livingPerson()->id])->save();

        $this->actingAs($user->fresh())
            ->postJson(route('api.v1.claims.store'), ['person_ulid' => $this->livingPerson()->ulid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ALREADY_CLAIMED');
    }

    public function test_a_person_the_claimant_cannot_see_returns_404(): void
    {
        // Claiming must not become a way to discover who exists.
        $hidden = Person::factory()->bornExactly(1985)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Private,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.claims.store'), ['person_ulid' => $hidden->ulid])
            ->assertNotFound();
    }

    public function test_approval_links_the_account_and_is_audited(): void
    {
        $claimant = $this->member();
        $person = $this->livingPerson();

        $claim = ProfileClaim::create([
            'user_id' => $claimant->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Pending,
        ]);

        $this->actingAs($this->familyAdmin())
            ->postJson(route('api.v1.claims.approve', $claim), ['note' => 'Confirmed by his uncle.'])
            ->assertOk()
            ->assertJsonPath('data.status', ClaimStatus::Approved->value);

        $this->assertSame($person->id, $claimant->fresh()->person_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'claim.approved']);
    }

    public function test_nobody_can_decide_their_own_claim(): void
    {
        // Otherwise a family admin could quietly claim any living relative.
        $admin = $this->familyAdmin();
        $person = $this->livingPerson();

        $claim = ProfileClaim::create([
            'user_id' => $admin->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->postJson(route('api.v1.claims.approve', $claim))
            ->assertForbidden();

        $this->assertNull($admin->fresh()->person_id);
    }

    public function test_a_member_without_standing_cannot_approve(): void
    {
        $claim = ProfileClaim::create([
            'user_id' => $this->member()->id,
            'person_id' => $this->livingPerson()->id,
            'status' => ClaimStatus::Pending,
        ]);

        $this->actingAs($this->member())
            ->postJson(route('api.v1.claims.approve', $claim))
            ->assertForbidden();
    }

    public function test_a_claim_is_rechecked_at_approval_time(): void
    {
        // Somebody else may have been verified as this person while the claim
        // sat in the queue.
        $person = $this->livingPerson();
        $claim = ProfileClaim::create([
            'user_id' => $this->member()->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Pending,
        ]);

        User::factory()->create()->forceFill(['person_id' => $person->id])->save();

        $this->actingAs($this->familyAdmin())
            ->postJson(route('api.v1.claims.approve', $claim))
            ->assertStatus(422)
            ->assertJsonPath('code', 'PERSON_ALREADY_CLAIMED');
    }

    public function test_a_rejected_claim_can_be_resubmitted(): void
    {
        $claimant = $this->member();
        $person = $this->livingPerson();

        $claim = ProfileClaim::create([
            'user_id' => $claimant->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Rejected,
        ]);

        $this->actingAs($claimant)
            ->postJson(route('api.v1.claims.store'), [
                'person_ulid' => $person->ulid,
                'evidence' => 'Adding my baptism certificate.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ClaimStatus::Pending->value);

        $this->assertSame(1, ProfileClaim::count(), 'The same row is reopened');
        $this->assertSame($claim->id, ProfileClaim::first()->id);
    }

    public function test_a_claimant_sees_only_their_own_claims(): void
    {
        $mine = ProfileClaim::create([
            'user_id' => ($me = $this->member())->id,
            'person_id' => $this->livingPerson()->id,
            'status' => ClaimStatus::Pending,
        ]);

        ProfileClaim::create([
            'user_id' => $this->member()->id,
            'person_id' => $this->livingPerson()->id,
            'status' => ClaimStatus::Pending,
        ]);

        $response = $this->actingAs($me)->getJson(route('api.v1.claims.index'))->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($mine->ulid, $response->json('data.0.ulid'));
    }
}
