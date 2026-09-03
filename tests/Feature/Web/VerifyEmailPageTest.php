<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyEmailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** The link Laravel signs, rather than one this test invents. */
    private function linkFor(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1((string) $user->getEmailForVerification()),
        ]);
    }

    public function test_registering_sends_a_verification_email_that_renders(): void
    {
        Notification::fake();

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Dam Mang',
            'email' => 'dam@example.com',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])->assertCreated();

        $user = User::where('email', 'dam@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $mail) use ($user): bool {
            // Rendering is where a missing route would throw, which is exactly
            // how the password reset link shipped broken.
            $this->assertStringContainsString('/verify-email/', $mail->toMail($user)->actionUrl);

            return true;
        });
    }

    public function test_opening_the_link_verifies_the_address(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->get($this->linkFor($user))
            ->assertOk()
            ->assertSee('Your email is confirmed');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_opening_the_same_link_twice_says_so_rather_than_failing(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->get($link)->assertOk();
        $this->get($link)->assertOk()->assertSee('This was already confirmed');
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1((string) $user->email),
        ]))->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_a_link_stops_working_once_the_address_changes(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $link = $this->linkFor($user);

        $user->forceFill(['email' => 'new@example.com'])->save();

        $this->get($link)->assertOk()->assertSee('That link no longer works');
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_changing_an_address_unverifies_it_and_sends_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patchJson(route('api.v1.auth.profile'), ['email' => 'new@example.com'])
            ->assertOk();

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_verified_account_asking_again_is_answered_but_sent_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.auth.email.resend'))
            ->assertOk();

        Notification::assertNothingSentTo($user);
    }
}
