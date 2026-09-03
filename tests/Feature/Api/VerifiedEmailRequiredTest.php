<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SyncOperation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contributing requires a confirmed address; reading does not.
 *
 * The split matters: making the whole app unusable until somebody checks their
 * mail is a different product decision than declining to attribute a permanent
 * record to an address nobody has shown they can receive at.
 */
class VerifiedEmailRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function unverifiedContributor(): User
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('contributor');

        return $user;
    }

    public function test_an_unverified_account_cannot_add_to_the_archive(): void
    {
        $this->actingAs($this->unverifiedContributor())
            ->postJson(route('api.v1.people.store'), ['first_name' => 'Thawng'])
            ->assertForbidden()
            // A generic 403 would send the client to the wrong screen.
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_an_unverified_account_can_still_read(): void
    {
        $this->actingAs($this->unverifiedContributor())
            ->getJson(route('api.v1.people.index'))
            ->assertOk();
    }

    public function test_an_unverified_account_can_still_fix_its_address_and_ask_again(): void
    {
        $user = $this->unverifiedContributor();

        // Gating these would trap somebody who mistyped their address.
        $this->actingAs($user)
            ->patchJson(route('api.v1.auth.profile'), ['email' => 'corrected@example.com'])
            ->assertOk();

        $this->actingAs($user->refresh())
            ->postJson(route('api.v1.auth.email.resend'))
            ->assertOk();
    }

    public function test_a_verified_account_contributes_normally(): void
    {
        $user = User::factory()->create();
        $user->assignRole('contributor');

        $this->actingAs($user)
            ->postJson(route('api.v1.people.store'), ['first_name' => 'Thawng'])
            ->assertCreated();
    }

    public function test_a_refused_write_does_not_burn_the_operation_id(): void
    {
        $user = $this->unverifiedContributor();

        $this->actingAs($user)->postJson(
            route('api.v1.people.store'),
            ['first_name' => 'Thawng'],
            ['Idempotency-Key' => 'op-that-should-survive'],
        )->assertForbidden();

        // Nothing was claimed, so the same id still works once they verify.
        $this->assertSame(0, SyncOperation::query()->count());
    }
}
