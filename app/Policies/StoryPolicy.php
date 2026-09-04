<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Story;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScope;

/**
 * Authorization for family narratives.
 */
class StoryPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    /**
     * Listing is allowed; what comes back is scoped by Story::visibleTo.
     * A guest sees public stories and nothing else.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Answered by the same query scope the listing uses, rather than a second
     * copy of the rule here. This returned true for everybody until stories
     * became reachable over the API — harmless while nothing could ask, and a
     * way to read another family's private history the moment something could.
     */
    public function view(?User $user, Story $story): bool
    {
        return $story->isVisibleTo(app(ViewerScope::class));
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'stories.create')
            || $this->permissions->scopePathsFor($user, 'stories.create') !== [];
    }

    public function update(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.update', $this->scopePathFor($story));
    }

    public function delete(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.delete', $this->scopePathFor($story));
    }

    public function verify(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.verify', $this->scopePathFor($story));
    }
}
