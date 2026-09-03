<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeRequestAwaitingReview;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tells the people who can actually decide a proposal that it exists.
 *
 * A review queue nobody is told about is a queue nobody opens, and the
 * contributor concludes the app swallowed their correction. This is the reason
 * push is worth having in a genealogy app at all: contributions arrive weeks
 * apart, so nobody is sitting watching for them.
 */
class NotifyReviewers
{
    /**
     * Enough for a family archive, and a hard stop so a misconfigured scope
     * cannot fan a single edit out to thousands of devices.
     */
    private const MAX_RECIPIENTS = 50;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function handle(ChangeRequest $request): int
    {
        if ($request->scope_id === null) {
            return 0;
        }

        $path = DB::table('scopes')->where('id', $request->scope_id)->value('path');

        if ($path === null) {
            return 0;
        }

        $recipients = $this->reviewersFor((string) $path)
            // Never tell somebody about their own suggestion.
            ->reject(fn (User $user) => $user->getKey() === $request->requested_by)
            ->take(self::MAX_RECIPIENTS);

        foreach ($recipients as $reviewer) {
            $reviewer->notify(new ChangeRequestAwaitingReview($request));
        }

        return $recipients->count();
    }

    /**
     * Members of the scope, or of anything above it, who may approve changes.
     *
     * Scope paths are materialised, so "anything above it" is a prefix match
     * rather than a walk up the tree.
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
                || $this->permissions->can($user, 'changes.approve', $path));
    }

    /**
     * Every prefix of a materialised path: /1/14/57/ yields /1/, /1/14/ and
     * /1/14/57/. A tribe administrator reviews what happens in its branches.
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
