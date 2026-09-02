<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\ContributionStat;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Decides whether a write lands directly or becomes a change request.
 *
 * The rule that matters: verified genealogy is never silently overwritten. A
 * contributor correcting a verified birth year files a proposal; somebody with
 * verify permission in that scope edits it outright. Both are legitimate
 * outcomes of a collaborative archive.
 *
 * The trust ramp routes a brand-new contributor's first few submissions through
 * review even on unverified records. It ships disabled — a ramp without a
 * review queue would trap new contributors with nowhere for their proposals to
 * go. It is enabled in the phase that builds the queue.
 */
class WriteGate
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    /**
     * @param  Model|null  $target  null for a create
     * @param  string  $group  permission group, e.g. 'people'
     */
    public function isDirect(User $user, ?Model $target, string $group, ?string $scopePath): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ($this->isWithinTrustRamp($user)) {
            return false;
        }

        // An unverified record is editable by anyone the policy already allowed.
        if ($target === null || ! method_exists($target, 'isLocked') || ! $target->isLocked()) {
            return true;
        }

        return $this->permissions->can($user, "{$group}.verify", $scopePath);
    }

    public function isWithinTrustRamp(User $user): bool
    {
        $threshold = (int) config('genealogy.trust_ramp');

        if ($threshold <= 0) {
            return false;
        }

        return $this->acceptedContributions($user) < $threshold;
    }

    private function acceptedContributions(User $user): int
    {
        $stats = ContributionStat::find($user->getKey());

        if ($stats === null) {
            return 0;
        }

        return (int) $stats->people_added
            + (int) $stats->relationships_added
            + (int) $stats->unions_added
            + (int) $stats->events_added;
    }
}
