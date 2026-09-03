<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Membership;
use App\Models\User;
use App\Notifications\MembershipRequestAwaitingReview;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tells the people who can decide a membership request that one exists.
 *
 * Without this, "Ask to join" was a request into the void: RequestMembership
 * wrote a Pending row and nothing else happened until an administrator
 * happened to open the members list. Mirrors NotifyReviewers, which does the
 * same thing for change requests, but the two cannot share one implementation
 * because they answer a different question — "who may edit here" is not "who
 * may decide who belongs here" — so they are gated on different permissions.
 */
class NotifyMembershipReviewers
{
    /** Same cap as NotifyReviewers, for the same reason: a misconfigured
     * scope must not fan one request out to thousands of devices. */
    private const MAX_RECIPIENTS = 50;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function handle(Membership $membership): int
    {
        $membership->loadMissing('scope');

        if ($membership->scope === null) {
            return 0;
        }

        $recipients = $this->reviewersFor((string) $membership->scope->path)
            // The applicant cannot administer their own request — but if they
            // somehow already did (a tribe admin joining a second tribe),
            // being told about their own request would be noise.
            ->reject(fn (User $user) => $user->getKey() === $membership->user_id)
            ->take(self::MAX_RECIPIENTS);

        foreach ($recipients as $reviewer) {
            $reviewer->notify(new MembershipRequestAwaitingReview($membership));
        }

        return $recipients->count();
    }

    /**
     * Members of the scope, or of anything above it, who may decide belonging.
     *
     * @return Collection<int, User>
     */
    private function reviewersFor(string $path): Collection
    {
        $ancestorPaths = $this->ancestorsOf($path);

        $scopeIds = DB::table('scopes')->whereIn('path', $ancestorPaths)->pluck('id');

        $userIds = DB::table('memberships')
            ->whereIn('scope_id', $scopeIds)
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->where('status', 'active')
            // Loaded up front: the permission check below runs per candidate,
            // and without this it is one query each — the exact N+1 the lazy
            // loading guard exists to catch.
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(fn (User $user) => $user->is_super_admin
                || $this->permissions->administersMembership($user, $path));
    }

    /**
     * Every prefix of a materialised path: /1/14/57/ yields /1/, /1/14/ and
     * /1/14/57/. An administrator of the tribe reviews requests in its
     * branches too.
     *
     * @return array<int, string>
     */
    private function ancestorsOf(string $path): array
    {
        $segments = array_values(array_filter(explode('/', $path)));
        $paths = [];
        $current = '/';

        foreach ($segments as $segment) {
            $current .= $segment.'/';
            $paths[] = $current;
        }

        return $paths;
    }
}
