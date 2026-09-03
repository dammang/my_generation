<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The reset link is built by a closure in AppServiceProvider, because Laravel's
 * default points at a `password.reset` route that only a web application has.
 *
 * Asserting that the endpoint returns 200 is not enough to catch that: the URL
 * is built inside the notification, so a test that never renders the mail
 * passes while a locked-out person gets a 500. Every test here renders it.
 */
class PasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_asking_for_a_reset_builds_a_link_that_can_be_rendered(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'dam@example.com']);

        $this->postJson(route('api.v1.auth.forgot'), ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            // Rendering is the point: this is what threw RouteNotFoundException.
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith(config('app.url').'/reset-password?', $url);

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email'] ?? null);
            $this->assertNotEmpty($query['token'] ?? null);

            return true;
        });
    }

    public function test_the_link_points_at_the_configured_domain(): void
    {
        config(['app.url' => 'https://khanggui.com']);
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.forgot'), ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->assertStringStartsWith(
                'https://khanggui.com/reset-password?',
                $notification->toMail($user)->actionUrl
            );

            return true;
        });
    }

    public function test_an_unknown_address_is_answered_the_same_way_and_sends_nothing(): void
    {
        Notification::fake();

        // Same body as a real address, or the endpoint tells a stranger which
        // of their guesses have accounts.
        $this->postJson(route('api.v1.auth.forgot'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'If that email address has an account, a reset link is on its way.');

        Notification::assertNothingSent();
    }
}
