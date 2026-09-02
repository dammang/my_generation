<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;

/**
 * Authorization for person records.
 *
 * `view` defers to PersonVisibilityResolver so there is one answer to "may this
 * viewer see this person", shared with the query scope and the field mask.
 * Write abilities defer to PermissionResolver, which understands scoped roles.
 */
class PersonPolicy
{
    use ResolvesScopePath;

    public function __construct(
        private readonly PersonVisibilityResolver $visibility,
        private readonly PermissionResolver $permissions,
        private readonly ViewerScope $viewer,
    ) {}

    public function viewAny(?User $user): bool
    {
        return true;   // the query scope decides which rows come back
    }

    public function view(?User $user, Person $person): bool
    {
        return $this->visibility->canView($this->viewer, $person);
    }

    /** The full record, including fields the mask would otherwise withhold. */
    public function viewFull(?User $user, Person $person): bool
    {
        return ! $this->visibility->mask($this->viewer, $person)->redacted;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'people.create')
            || $this->permissions->scopePathsFor($user, 'people.create') !== [];
    }

    public function update(User $user, Person $person): bool
    {
        if (! $this->view($user, $person)) {
            return false;
        }

        return $this->permissions->can($user, 'people.update', $this->scopePathFor($person));
    }

    /**
     * Editing a *verified* record needs verify permission. Without it the write
     * is not refused — it becomes a change request, which is the point of the
     * collaborative model rather than a consolation prize.
     */
    public function updateDirectly(User $user, Person $person): bool
    {
        if (! $this->update($user, $person)) {
            return false;
        }

        if (! $person->isLocked()) {
            return true;
        }

        return $this->verify($user, $person);
    }

    public function delete(User $user, Person $person): bool
    {
        return $this->view($user, $person)
            && $this->permissions->can($user, 'people.delete', $this->scopePathFor($person));
    }

    public function verify(User $user, Person $person): bool
    {
        return $this->permissions->can($user, 'people.verify', $this->scopePathFor($person));
    }

    public function merge(User $user, Person $person): bool
    {
        return $this->permissions->can($user, 'people.merge', $this->scopePathFor($person));
    }
}
