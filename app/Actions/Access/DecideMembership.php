<?php

declare(strict_types=1);

namespace App\Actions\Access;

use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
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

            $this->scopes->forget($membership->loadMissing('user')->user);

            return $membership;
        });
    }
}
