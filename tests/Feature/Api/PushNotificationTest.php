<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use App\Models\Clan;
use App\Models\DeviceToken;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Notifications\ChangeRequestAwaitingReview;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Telling the right people, and only them.
 *
 * The push is a convenience; the database notification is the record. Somebody
 * whose phone was off must still find the thing waiting in the app.
 */
class PushNotificationTest extends TestCase
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

    private function verifiedPerson(): Person
    {
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Public,
            'is_living' => false,
            'first_name' => 'Thawng',
            'verification_status' => VerificationStatus::Verified,
        ]);
    }

    public function test_a_device_registration_is_recorded(): void
    {
        $user = $this->memberWithRole('contributor');

        $this->actingAs($user)
            ->postJson(route('api.v1.devices.store'), [
                'token' => 'fcm-token-1',
                'platform' => 'ios',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.registered', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
        ]);
    }

    public function test_a_registration_moves_with_the_phone_not_the_account(): void
    {
        // FCM reissues a registration to whichever account most recently
        // claimed it. Keyed on the user instead, a shared phone would keep
        // delivering one family's notifications to somebody else.
        $first = $this->memberWithRole('contributor');
        $second = $this->memberWithRole('contributor');

        $this->actingAs($first)
            ->postJson(route('api.v1.devices.store'), ['token' => 'shared', 'platform' => 'android'])
            ->assertOk();

        $this->actingAs($second)
            ->postJson(route('api.v1.devices.store'), ['token' => 'shared', 'platform' => 'android'])
            ->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'shared')->count());
        $this->assertSame($second->id, DeviceToken::where('token', 'shared')->value('user_id'));
    }

    public function test_signing_out_can_remove_the_registration(): void
    {
        $user = $this->memberWithRole('contributor');

        $this->actingAs($user)
            ->postJson(route('api.v1.devices.store'), ['token' => 'fcm-1', 'platform' => 'ios'])
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson(route('api.v1.devices.destroy'), ['token' => 'fcm-1'])
            ->assertNoContent();

        // The next person to use that phone must not receive notifications
        // about a family they have nothing to do with.
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-1']);
    }

    public function test_a_reviewer_is_told_about_a_suggestion(): void
    {
        Notification::fake();

        $person = $this->verifiedPerson();
        $reviewer = $this->memberWithRole('tribe-admin');
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        Notification::assertSentTo($reviewer, ChangeRequestAwaitingReview::class);
    }

    public function test_the_contributor_is_not_told_about_their_own_suggestion(): void
    {
        Notification::fake();

        $person = $this->verifiedPerson();
        $this->memberWithRole('tribe-admin');
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        Notification::assertNotSentTo($contributor, ChangeRequestAwaitingReview::class);
    }

    public function test_somebody_who_cannot_review_is_not_told(): void
    {
        Notification::fake();

        $person = $this->verifiedPerson();
        $bystander = $this->memberWithRole('viewer');
        $contributor = $this->memberWithRole('contributor');

        $this->actingAs($contributor)
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        // A notification about a queue somebody cannot act on is noise, and it
        // tells them a correction exists that they may not be entitled to see.
        Notification::assertNotSentTo($bystander, ChangeRequestAwaitingReview::class);
    }

    public function test_the_notification_is_recorded_even_without_a_device(): void
    {
        $person = $this->verifiedPerson();
        $reviewer = $this->memberWithRole('tribe-admin');

        $this->actingAs($this->memberWithRole('contributor'))
            ->patchJson(route('api.v1.people.update', $person), ['first_name' => 'Thang'])
            ->assertStatus(202);

        // No device registered at all. The push is the convenience; the record
        // in the app is what somebody comes back to.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $reviewer->id,
            'type' => ChangeRequestAwaitingReview::class,
        ]);
    }
}
