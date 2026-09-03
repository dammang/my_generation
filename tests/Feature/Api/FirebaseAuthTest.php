<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Signature;
use Mockery;
use Tests\TestCase;

/**
 * Exchanging a Firebase identity for a session here.
 *
 * Firebase answers who somebody is. It knows nothing about suspension, tribes
 * or roles — so a verified identity is where authorization *starts*, not
 * something that bypasses it.
 */
class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * A verified ID token, as the SDK hands it back after checking the
     * signature, audience, issuer and expiry.
     *
     * @param  array<string, mixed>  $claims
     */
    private function tokenFor(array $claims): Plain
    {
        return new Plain(
            new DataSet(['alg' => 'RS256'], ''),
            new DataSet($claims, ''),
            new Signature('', ''),
        );
    }

    /** @param  array<string, mixed>  $claims */
    private function firebaseReturns(array $claims): void
    {
        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('verifyIdToken')->andReturn($this->tokenFor($claims));

        $this->app->instance(FirebaseAuth::class, $auth);
    }

    private function firebaseRejects(): void
    {
        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('verifyIdToken')
            ->andThrow(new FailedToVerifyToken('bad signature'));

        $this->app->instance(FirebaseAuth::class, $auth);
    }

    public function test_a_new_google_identity_creates_an_account(): void
    {
        $this->firebaseReturns([
            'sub' => 'uid-google-1',
            'email' => 'cin@example.com',
            'email_verified' => true,
            'name' => 'Cin Hlei',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ]);

        $response = $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertStatus(201)
            ->assertJsonPath('data.created', true);

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'cin@example.com')->firstOrFail();

        $this->assertSame('uid-google-1', $user->firebase_uid);
        $this->assertSame('google.com', $user->auth_provider);
        // An account made through Google has no password here; the identity
        // lives with the provider.
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_returning_with_the_same_identity_signs_in(): void
    {
        $claims = [
            'sub' => 'uid-google-1',
            'email' => 'cin@example.com',
            'email_verified' => true,
            'name' => 'Cin Hlei',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ];

        $this->firebaseReturns($claims);
        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])->assertStatus(201);

        $this->firebaseReturns($claims);
        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertSame(1, User::where('email', 'cin@example.com')->count());
    }

    public function test_a_verified_email_links_to_an_existing_account(): void
    {
        // Somebody who signed up with a password and now uses Google. Their
        // tribes, roles and family must follow them, not be left behind on a
        // second account.
        $existing = User::factory()->create([
            'email' => 'cin@example.com',
            'firebase_uid' => null,
        ]);

        $this->firebaseReturns([
            'sub' => 'uid-google-1',
            'email' => 'cin@example.com',
            'email_verified' => true,
            'name' => 'Cin Hlei',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ]);

        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])->assertOk();

        $this->assertSame('uid-google-1', $existing->fresh()->firebase_uid);
        $this->assertSame(1, User::where('email', 'cin@example.com')->count());
    }

    public function test_an_unverified_email_cannot_claim_an_existing_account(): void
    {
        // The takeover this guards against: anybody able to create a Firebase
        // account claiming an address would otherwise inherit that person's
        // tribes, roles and family.
        $existing = User::factory()->create([
            'email' => 'cin@example.com',
            'firebase_uid' => null,
        ]);

        $this->firebaseReturns([
            'sub' => 'uid-attacker',
            'email' => 'cin@example.com',
            'email_verified' => false,
            'name' => 'Not Cin',
            'firebase' => ['sign_in_provider' => 'password'],
        ]);

        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'FIREBASE_SIGN_IN_REFUSED');

        $this->assertNull($existing->fresh()->firebase_uid);
    }

    public function test_a_suspended_account_is_refused_despite_a_valid_identity(): void
    {
        User::factory()->create([
            'email' => 'cin@example.com',
            'firebase_uid' => 'uid-google-1',
            'status' => UserStatus::Suspended,
        ]);

        $this->firebaseReturns([
            'sub' => 'uid-google-1',
            'email' => 'cin@example.com',
            'email_verified' => true,
            'firebase' => ['sign_in_provider' => 'google.com'],
        ]);

        // Suspension is a decision made here. Firebase knows nothing about it,
        // and a valid identity is not an entitlement.
        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertStatus(401);
    }

    public function test_an_unverifiable_token_is_refused(): void
    {
        $this->firebaseRejects();

        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'forged'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'FIREBASE_SIGN_IN_REFUSED');

        $this->assertSame(0, User::count());
    }

    public function test_an_identity_without_an_email_is_refused(): void
    {
        // Apple's "hide my email" can be relayed, but a provider giving nothing
        // at all leaves no way to link or contact the account.
        $this->firebaseReturns([
            'sub' => 'uid-apple-1',
            'email' => null,
            'email_verified' => false,
            'firebase' => ['sign_in_provider' => 'apple.com'],
        ]);

        $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertStatus(401);
    }

    public function test_the_exchanged_token_works_on_the_rest_of_the_api(): void
    {
        $this->firebaseReturns([
            'sub' => 'uid-google-1',
            'email' => 'cin@example.com',
            'email_verified' => true,
            'name' => 'Cin Hlei',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ]);

        $token = $this->postJson(route('api.v1.auth.firebase'), ['id_token' => 'x'])
            ->assertStatus(201)
            ->json('data.token');

        // The whole point: everything downstream is unchanged.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'cin@example.com');
    }
}
