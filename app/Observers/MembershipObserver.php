<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Membership;
use App\Services\Privacy\ViewerScopeResolver;

/**
 * Keeps a cached viewer scope honest.
 *
 * The actions that grant and revoke membership already invalidate the cache,
 * but they are not the only way a row gets written — a seeder, the admin panel
 * or a future action would leave somebody with a stale scope until the TTL
 * expired, which presents as "I joined but still cannot see anything".
 */
class MembershipObserver
{
    public function __construct(private readonly ViewerScopeResolver $scopes) {}

    public function saved(Membership $membership): void
    {
        $this->forget($membership);
    }

    public function deleted(Membership $membership): void
    {
        $this->forget($membership);
    }

    private function forget(Membership $membership): void
    {
        $user = $membership->loadMissing('user')->user;

        if ($user !== null) {
            $this->scopes->forget($user);
        }
    }
}
