<?php

declare(strict_types=1);

namespace App\Actions\Access;

use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Support\Facades\DB;

/**
 * Approving a membership widens what somebody can see, so it is an
 * administrative act: recorded in audit_logs, and it invalidates the applicant's
 * cached entitlements immediately rather than at the next TTL expiry.
 */
class DecideMembership
{
    public function __construct(private readonly ViewerScopeResolver $scopes) {}

    public function handle(Membership $membership, MembershipStatus $decision, User $decidedBy): Membership
    {
        return DB::transaction(function () use ($membership, $decision, $decidedBy): Membership {
            $membership->status = $decision;
            $membership->approved_by = $decidedBy->getKey();
            $membership->approved_at = $decision === MembershipStatus::Active ? now() : null;
            $membership->save();

            AuditLog::create([
                'user_id' => $decidedBy->getKey(),
                'action' => 'membership.'.$decision->value,
                'auditable_type' => $membership->getMorphClass(),
                'auditable_id' => $membership->getKey(),
                'context' => [
                    'subject_user_id' => $membership->user_id,
                    'scope_id' => $membership->scope_id,
                ],
            ]);

            if ($decision === MembershipStatus::Active) {
                $this->carryTheTribe($membership);
            }

            $this->scopes->forget($membership->loadMissing('user')->user);

            return $membership;
        });
    }

    /**
     * A clan sits inside a tribe, so being let into one is being let into the
     * other.
     *
     * Granted here rather than asked for separately: nobody joins the Zomi in
     * order to join JK, and a member without the tribe was invisible to
     * everything counted at tribe level while plainly belonging to it.
     */
    private function carryTheTribe(Membership $membership): void
    {
        $scope = $membership->loadMissing('scope.scopeable')->scope;

        if ($scope?->scopeable_type !== 'clan') {
            return;
        }

        $tribeId = $scope->scopeable?->tribe_id;

        $tribeScope = $tribeId === null ? null : Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $tribeId)
            ->first();

        if ($tribeScope === null) {
            return;
        }

        Membership::updateOrCreate(
            ['user_id' => $membership->user_id, 'scope_id' => $tribeScope->getKey()],
            [
                'status' => MembershipStatus::Active,
                'approved_by' => $membership->approved_by,
                'approved_at' => now(),
            ],
        );
    }
}
