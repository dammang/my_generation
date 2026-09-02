<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_returns_201_with_a_token(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ]);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('success', true)
                ->has('data.token')
                ->where('data.user.email', 'dam@example.com')
                ->etc());

        $this->assertDatabaseHas('users', ['email' => 'dam@example.com']);
    }

    public function test_registration_never_links_an_account_to_a_genealogy_record(): void
    {
        // Linking happens only through an approved profile claim; otherwise
        // anyone could register as somebody's deceased grandfather.
        Person::factory()->create();

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])->assertCreated();

        $this->assertNull(User::where('email', 'dam@example.com')->value('person_id'));
    }

    public function test_a_new_account_is_a_contributor(): void
    {
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])->assertCreated();

        $this->assertTrue(User::where('email', 'dam@example.com')->first()->hasRole('contributor'));
    }

    public function test_registration_returns_422_for_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dam@example.com']);

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_registration_returns_422_for_a_weak_password(): void
    {
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_login_returns_a_token(): void
    {
        User::factory()->create(['email' => 'dam@example.com', 'password' => 'correct-horse-9']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_returns_422_for_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'dam@example.com', 'password' => 'correct-horse-9']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com',
            'password' => 'wrong-password-1',
        ])->assertStatus(422);
    }

    public function test_login_does_not_reveal_whether_an_account_exists(): void
    {
        User::factory()->create(['email' => 'known@example.com', 'password' => 'correct-horse-9']);

        $known = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'known@example.com', 'password' => 'wrong-password-1',
        ]);

        $unknown = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'unknown@example.com', 'password' => 'wrong-password-1',
        ]);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame(
            $known->json('errors.email'),
            $unknown->json('errors.email'),
            'The error must not distinguish a missing account from a wrong password'
        );
    }

    public function test_a_suspended_account_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
        ])->assertStatus(422);
    }

    public function test_me_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.auth.me'))
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_me_returns_the_signed_in_account_with_its_scopes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['scopes' => ['tribe_ids', 'clan_ids', 'branch_ids'], 'permissions']]);
    }

    public function test_logout_revokes_only_the_presented_token(): void
    {
        // Signing out on a phone must not sign the same person out elsewhere.
        $user = User::factory()->create(['password' => 'correct-horse-9']);
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withHeader('Authorization', "Bearer {$phone}")
            ->postJson(route('api.v1.auth.logout'))
            ->assertOk();

        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_logout_everywhere_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.v1.auth.logout-everywhere'))
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_updating_the_email_clears_its_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patchJson(route('api.v1.auth.profile'), ['email' => 'new@example.com'])
            ->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_forgot_password_answers_identically_for_unknown_addresses(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson(route('api.v1.auth.forgot'), ['email' => 'known@example.com']);
        $unknown = $this->postJson(route('api.v1.auth.forgot'), ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('data.message'), $unknown->json('data.message'));
    }

    public function test_sign_in_is_written_to_the_audit_log(): void
    {
        $user = User::factory()->create(['email' => 'dam@example.com', 'password' => 'correct-horse-9']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com', 'password' => 'correct-horse-9',
        ])->assertOk();

        $this->assertTrue(
            AuditLog::where('action', 'auth.logged_in')->where('user_id', $user->id)->exists()
        );
    }

    public function test_the_audit_log_stores_a_hashed_ip_not_a_raw_one(): void
    {
        $user = User::factory()->create(['email' => 'dam@example.com', 'password' => 'correct-horse-9']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com', 'password' => 'correct-horse-9',
        ]);

        $hash = AuditLog::where('user_id', $user->id)->value('ip_hash');

        $this->assertSame(64, strlen((string) $hash));
        $this->assertStringNotContainsString('127.0.0.1', (string) $hash);
    }
}
