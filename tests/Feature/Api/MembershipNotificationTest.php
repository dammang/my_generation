<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Notifications\MembershipRequestAwaitingReview;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Ask to join" used to write a Pending row and stop — nobody was told a
 * membership was waiting until an administrator happened to open the list.
 * Mirrors PushNotificationTest's coverage of the sibling change-request flow,
 * gated on a different permission because the two answer different questions.
 */
class MembershipNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
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

    public function test_an_administrator_is_told_about_a_membership_request(): void
    {
        Notification::fake();

        $admin = $this->memberWithRole('tribe-admin');
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        Notification::assertSentTo($admin, MembershipRequestAwaitingReview::class);
    }

    public function test_the_applicant_is_not_told_about_their_own_request(): void
    {
        Notification::fake();

        $this->memberWithRole('tribe-admin');
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        Notification::assertNotSentTo($applicant, MembershipRequestAwaitingReview::class);
    }

    public function test_somebody_who_cannot_approve_memberships_is_not_told(): void
    {
        Notification::fake();

        // A contributor edits genealogy freely but does not decide who joins —
        // the two are gated on different permissions on purpose.
        $bystander = $this->memberWithRole('contributor');
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        Notification::assertNotSentTo($bystander, MembershipRequestAwaitingReview::class);
    }

    public function test_reactivating_an_already_active_membership_notifies_nobody(): void
    {
        Notification::fake();

        $admin = $this->memberWithRole('tribe-admin');
        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe->id)
            ->firstOrFail();

        $already = User::factory()->create();
        Membership::create([
            'user_id' => $already->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        // Nothing is actually pending here — RequestMembership returns the
        // existing active row untouched, so there is nothing to review.
        $this->actingAs($already)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        Notification::assertNotSentTo($admin, MembershipRequestAwaitingReview::class);
    }

    public function test_the_notification_is_recorded_even_without_a_device(): void
    {
        $admin = $this->memberWithRole('tribe-admin');
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => MembershipRequestAwaitingReview::class,
        ]);
    }
}
