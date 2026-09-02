<?php

declare(strict_types=1);

namespace App\Actions\Claims;

use App\Enums\ClaimStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\Person;
use App\Models\ProfileClaim;
use App\Models\User;

/**
 * "This person is me."
 *
 * A genealogy record usually exists before the person it describes ever opens
 * the app — an uncle added them years ago. Claiming is how an account is joined
 * to its record, and it is deliberately a request rather than an action: the
 * claim grants family-scope visibility of everyone around that person, so
 * letting anybody self-assert it would be an open door into other families.
 */
class SubmitProfileClaim
{
    public function handle(User $user, Person $person, ?string $evidence, ?string $statement): ProfileClaim
    {
        $this->assertClaimable($user, $person);

        $claim = ProfileClaim::firstOrNew([
            'user_id' => $user->getKey(),
            'person_id' => $person->getKey(),
        ]);

        // Re-requesting after a rejection reopens the same row rather than
        // accumulating a history nobody reads.
        $claim->fill([
            'status' => ClaimStatus::Pending,
            'evidence' => $evidence,
            'relationship_statement' => $statement,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
        ])->save();

        return $claim;
    }

    private function assertClaimable(User $user, Person $person): void
    {
        if ($user->person_id !== null) {
            throw new GenealogyRuleException(
                'This account is already linked to a person in the archive.',
                'ALREADY_CLAIMED',
            );
        }

        // One person, one account. A second claimant on a record somebody has
        // already been verified as is a dispute, not a claim.
        if ($person->claimedByUser()->exists()) {
            throw new GenealogyRuleException(
                'Someone has already been verified as this person.',
                'PERSON_ALREADY_CLAIMED',
            );
        }

        if ($person->isDeceased()) {
            throw new GenealogyRuleException(
                'A deceased person cannot be claimed.',
                'PERSON_DECEASED',
            );
        }

        if ($person->isTombstone()) {
            throw new GenealogyRuleException(
                'This record was merged into another. Claim that one instead.',
                'PERSON_MERGED',
            );
        }
    }
}
