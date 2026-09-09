<?php

declare(strict_types=1);

namespace App\Actions\Access;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;

/**
 * "I belong to this tribe." Belonging, not capability — a membership grants
 * visibility of tribe-scoped records and nothing else.
 */
class RequestMembership
{
    public function __construct(private readonly ViewerScopeResolver $scopes) {}

    /**
     * @param  array<string, mixed>  $answers  what the applicant said about
     *                                         themselves, already validated
     */
    public function handle(User $user, Scope $scope, array $answers = []): Membership
    {
        $membership = Membership::firstOrNew([
            'user_id' => $user->getKey(),
            'scope_id' => $scope->getKey(),
        ]);

        // Re-requesting after leaving or being rejected reopens the same row
        // rather than accumulating history nobody reads.
        if ($membership->exists && $membership->status === MembershipStatus::Active) {
            return $membership;
        }

        $membership->status = MembershipStatus::Pending;
        $membership->approved_by = null;
        $membership->approved_at = null;

        // Only what was given. A second attempt after a rejection reuses the
        // row, and blanking a previous answer because this form omitted it
        // would quietly destroy what the applicant had already said.
        $membership->fill(array_filter(
            $answers,
            static fn ($value) => $value !== null,
        ));

        $membership->save();

        // A pending membership grants nothing, but the cached scope was built
        // before this row existed and would otherwise be stale for ten minutes.
        $this->scopes->forget($user);

        return $membership;
    }
}
