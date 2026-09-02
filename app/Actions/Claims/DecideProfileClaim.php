<?php

declare(strict_types=1);

namespace App\Actions\Claims;

use App\Enums\ClaimStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\AuditLog;
use App\Models\ProfileClaim;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Approving a claim links an account to a genealogy record.
 *
 * That is a bigger act than it looks: it also makes the claimant close kin of
 * everyone around that person, and so widens what they can see across a family.
 * It requires standing in the record's own scope, it is written to the audit
 * log, and it never happens automatically.
 */
class DecideProfileClaim
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ViewerScopeResolver $scopes,
    ) {}

    public function approve(ProfileClaim $claim, User $decidedBy, ?string $note = null): ProfileClaim
    {
        $claim->loadMissing(['person', 'user']);

        $this->assertMayDecide($claim, $decidedBy);

        if ($claim->status !== ClaimStatus::Pending) {
            throw new GenealogyRuleException('This claim has already been decided.', 'CLAIM_DECIDED');
        }

        // Re-checked at approval time, not at submission: somebody else may
        // have been verified as this person while the claim sat in the queue.
        if ($claim->person->claimedByUser()->exists()) {
            throw new GenealogyRuleException(
                'Someone else has been verified as this person since the claim was made.',
                'PERSON_ALREADY_CLAIMED',
            );
        }

        return DB::transaction(function () use ($claim, $decidedBy, $note): ProfileClaim {
            $claim->forceFill([
                'status' => ClaimStatus::Approved,
                'decided_by' => $decidedBy->getKey(),
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            // The only place users.person_id is ever written.
            $claim->user->forceFill(['person_id' => $claim->person_id])->save();

            AuditLog::create([
                'user_id' => $decidedBy->getKey(),
                'action' => 'claim.approved',
                'auditable_type' => $claim->getMorphClass(),
                'auditable_id' => $claim->getKey(),
                'context' => [
                    'claimant' => $claim->user->ulid,
                    'person' => $claim->person->ulid,
                ],
            ]);

            // Kinship now reaches further, so the cached entitlements are stale.
            $this->scopes->forget($claim->user);

            return $claim;
        });
    }

    public function reject(ProfileClaim $claim, User $decidedBy, ?string $note = null): ProfileClaim
    {
        $claim->loadMissing(['person', 'user']);

        $this->assertMayDecide($claim, $decidedBy);

        $claim->forceFill([
            'status' => ClaimStatus::Rejected,
            'decided_by' => $decidedBy->getKey(),
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        AuditLog::create([
            'user_id' => $decidedBy->getKey(),
            'action' => 'claim.rejected',
            'auditable_type' => $claim->getMorphClass(),
            'auditable_id' => $claim->getKey(),
            'context' => ['claimant' => $claim->user->ulid, 'person' => $claim->person->ulid],
        ]);

        return $claim;
    }

    private function assertMayDecide(ProfileClaim $claim, User $decidedBy): void
    {
        if ($decidedBy->is($claim->user)) {
            throw new AuthorizationException('You cannot decide your own claim.');
        }

        $scopePath = $this->scopePathFor($claim);

        if (! $this->permissions->can($decidedBy, 'claims.approve', $scopePath)) {
            throw new AuthorizationException('You may not decide claims in this scope.');
        }
    }

    /** The most specific scope the claimed person sits in. */
    private function scopePathFor(ProfileClaim $claim): ?string
    {
        $person = $claim->person;

        foreach ([
            ['family_branch', $person->family_branch_id],
            ['clan', $person->clan_id],
            ['tribe', $person->tribe_id],
        ] as [$type, $id]) {
            if ($id === null) {
                continue;
            }

            $path = DB::table('scopes')
                ->where('scopeable_type', $type)
                ->where('scopeable_id', $id)
                ->value('path');

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }
}
