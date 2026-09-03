<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Exceptions\FirebaseSignInRefused;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\UnencryptedToken;

/**
 * Turns a Firebase identity into a local session.
 *
 * Firebase answers who somebody is. It has no idea which tribe they belong to,
 * what they may edit, or whether an administrator has suspended them — all of
 * which live here. So the ID token is verified once, exchanged for a Sanctum
 * token, and never seen again: the rest of the API keeps working exactly as it
 * did, and a session can still be revoked server-side, which a Firebase token
 * cannot be.
 */
class ExchangeFirebaseToken
{
    public function __construct(private readonly FirebaseAuth $firebase) {}

    /**
     * @return array{user: User, created: bool}
     */
    public function handle(string $idToken, ?string $locale = null): array
    {
        $claims = $this->verify($idToken);

        $uid = (string) $claims->claims()->get('sub');
        $email = $claims->claims()->get('email');
        $emailVerified = (bool) $claims->claims()->get('email_verified', false);
        $name = (string) ($claims->claims()->get('name') ?? '');
        $picture = $claims->claims()->get('picture');
        $provider = $this->providerFrom($claims);

        return DB::transaction(function () use (
            $uid, $email, $emailVerified, $name, $picture, $provider, $locale
        ): array {
            $user = User::where('firebase_uid', $uid)->first();

            if ($user === null) {
                $user = $this->linkOrCreate($uid, $email, $emailVerified, $name, $locale);
            }

            if ($user->status !== UserStatus::Active) {
                // Suspension is a decision made here, and Firebase knows
                // nothing about it. A valid identity is not an entitlement.
                throw new FirebaseSignInRefused(
                    'This account is not active. Please contact an administrator.'
                );
            }

            $user->forceFill(array_filter([
                'auth_provider' => $provider,
                'avatar_url' => is_string($picture) ? $picture : null,
                'last_active_at' => now(),
                // Firebase verified the address; recording that here keeps the
                // local flag honest rather than leaving it perpetually null.
                'email_verified_at' => $emailVerified && $user->email_verified_at === null
                    ? now()
                    : $user->email_verified_at,
            ], fn ($value) => $value !== null))->save();

            return ['user' => $user, 'created' => $user->wasRecentlyCreated];
        });
    }

    /**
     * Finds an existing account by email, or makes a new one.
     *
     * Linking by email is only safe when the provider has verified it.
     * Otherwise anybody who can create a Firebase account claiming
     * somebody@example.com inherits that person's tribes, roles and family —
     * which is account takeover with extra steps.
     */
    private function linkOrCreate(
        string $uid,
        ?string $email,
        bool $emailVerified,
        string $name,
        ?string $locale,
    ): User {
        if ($email === null) {
            throw new FirebaseSignInRefused(
                'That sign-in did not provide an email address, which this app needs.'
            );
        }

        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            if (! $emailVerified) {
                throw new FirebaseSignInRefused(
                    'An account already uses this email address. Sign in the way '
                        .'you did before, or verify this address first.'
                );
            }

            $existing->forceFill(['firebase_uid' => $uid])->save();

            return $existing;
        }

        $user = new User([
            'name' => $name !== '' ? $name : explode('@', $email)[0],
            'email' => $email,
            'locale' => $locale ?? config('app.locale'),
            'status' => UserStatus::Active,
        ]);

        // Set outside mass assignment: the identity key is not something a
        // request field should ever be able to choose, and the allow-list on
        // User is what makes that guarantee.
        $user->forceFill([
            'firebase_uid' => $uid,
            'password' => null,
            'email_verified_at' => $emailVerified ? now() : null,
        ])->save();

        return $user;
    }

    private function verify(string $idToken): UnencryptedToken
    {
        try {
            // Checks the signature against Google's rotating keys, plus the
            // audience, issuer and expiry. Without this an ID token is just a
            // base64 string anybody can write.
            return $this->firebase->verifyIdToken($idToken, leewayInSeconds: 60);
        } catch (FailedToVerifyToken $e) {
            throw new FirebaseSignInRefused('That sign-in could not be verified. Please try again.');
        }
    }

    /** google.com, apple.com, or password. */
    private function providerFrom(UnencryptedToken $claims): ?string
    {
        $firebase = $claims->claims()->get('firebase');

        if (is_object($firebase) && isset($firebase->sign_in_provider)) {
            return (string) $firebase->sign_in_provider;
        }

        if (is_array($firebase) && isset($firebase['sign_in_provider'])) {
            return (string) $firebase['sign_in_provider'];
        }

        return null;
    }
}
