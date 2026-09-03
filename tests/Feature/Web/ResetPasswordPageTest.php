<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The page a reset email links to — the only web page this application serves.
 *
 * It is covered as a page rather than as an endpoint because the failure that
 * matters is somebody locked out of their account staring at a form that will
 * not work, which a status code does not describe.
 */
class ResetPasswordPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_link_from_an_email_opens_a_usable_form(): void
    {
        $user = User::factory()->create(['email' => 'dam@example.com']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new password')
            // The address is shown so nobody resets an account they did not mean to.
            ->assertSee('dam@example.com')
            ->assertSee($token, escape: false);
    }

    public function test_a_link_missing_its_token_explains_itself_instead_of_showing_a_dead_form(): void
    {
        $this->get(route('password.reset'))
            ->assertOk()
            ->assertSee('That link is not complete')
            ->assertDontSee('Set new password');
    }

    public function test_a_valid_token_changes_the_password_and_revokes_every_session(): void
    {
        $user = User::factory()->create([
            'email' => 'dam@example.com',
            'password' => Hash::make('the-old-one-1'),
        ]);
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-better-one-9',
            'password_confirmation' => 'a-better-one-9',
        ])->assertRedirect(route('password.reset.done'));

        $user->refresh();
        $this->assertTrue(Hash::check('a-better-one-9', $user->password));
        // A reset is how somebody recovers a compromised account, so whoever
        // else was signed in must be signed out.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_expired_link_says_so_and_keeps_the_form_usable(): void
    {
        $user = User::factory()->create(['email' => 'dam@example.com']);

        $response = $this->from(route('password.reset'))->post(route('password.update'), [
            'token' => 'a-token-that-was-never-issued',
            'email' => $user->email,
            'password' => 'a-better-one-9',
            'password_confirmation' => 'a-better-one-9',
        ]);

        $response->assertRedirect(route('password.reset'));
        $response->assertSessionHasErrors('password');
        // The token comes back, so a wrong password and a dead link are not
        // the same dead end.
        $this->assertSame('a-token-that-was-never-issued', session()->getOldInput('token'));
    }

    public function test_a_mistyped_confirmation_does_not_change_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'dam@example.com',
            'password' => Hash::make('the-old-one-1'),
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-better-one-9',
            'password_confirmation' => 'a-better-one-8',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('the-old-one-1', $user->refresh()->password));
    }

    public function test_the_page_is_kept_out_of_search_results(): void
    {
        // The URL contains a live reset token.
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@b.co']))
            ->assertSee('noindex', escape: false);
    }
}
