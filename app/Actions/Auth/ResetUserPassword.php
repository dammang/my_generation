<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Completes a password reset for both the API and the web page.
 *
 * The link in a reset email is opened in a browser, not in the app, so the
 * same reset has to work from a Blade form and from the mobile client. Keeping
 * it here means the part that matters — revoking every existing session —
 * cannot be correct in one path and forgotten in the other.
 */
class ResetUserPassword
{
    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return string One of the Password broker status constants.
     */
    public function handle(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            // Every existing session is invalidated: a password reset is how
            // somebody recovers an account that may be compromised.
            $user->tokens()->delete();

            event(new PasswordReset($user));
        });
    }
}
