<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One envelope everywhere, so the Flutter client has one parser and one error
 * path rather than a per-endpoint guess.
 */
class ResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_successful_response_carries_success_data_and_warnings(): void
    {
        $this->getJson(route('api.v1.health'))
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'warnings'])
            ->assertJsonPath('success', true)
            ->assertJsonPath('warnings', []);
    }

    public function test_a_failed_response_carries_message_errors_and_code(): void
    {
        $this->postJson(route('api.v1.auth.login'), [])
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors', 'code'])
            ->assertJsonPath('success', false);
    }

    public function test_an_unknown_route_returns_the_envelope_not_html(): void
    {
        $this->getJson('/api/v1/no-such-endpoint')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_list_response_carries_cursor_pagination_meta(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.index'))
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta' => ['per_page', 'has_more']]);
    }

    public function test_the_api_answers_json_even_without_an_accept_header(): void
    {
        // Otherwise Laravel redirects unauthenticated requests to a login route
        // that does not exist here, and the client sees a confusing 302.
        $this->get(route('api.v1.auth.me'))
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_auth_endpoints_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.auth.login'), [
                'email' => 'dam@example.com', 'password' => 'wrong-password-1',
            ]);
        }

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'dam@example.com', 'password' => 'wrong-password-1',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
    }

    public function test_the_auth_throttle_keys_on_the_email_as_well_as_the_ip(): void
    {
        // Limiting by IP alone lets a botnet spread a credential-stuffing run
        // across thousands of addresses. Exhaust the limit for one email from
        // one address, then attack the same email from a different one.
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson(route('api.v1.auth.login'), [
                    'email' => 'target@example.com', 'password' => 'wrong-password-1',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->postJson(route('api.v1.auth.login'), [
                'email' => 'target@example.com', 'password' => 'wrong-password-1',
            ])
            ->assertStatus(429);
    }

    public function test_a_different_email_from_a_fresh_address_is_not_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson(route('api.v1.auth.login'), [
                    'email' => 'target@example.com', 'password' => 'wrong-password-1',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->postJson(route('api.v1.auth.login'), [
                'email' => 'someone-else@example.com', 'password' => 'wrong-password-1',
            ])
            ->assertStatus(422);
    }
}
