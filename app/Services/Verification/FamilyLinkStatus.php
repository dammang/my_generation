<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\FamilyBranch;
use App\Models\Person;

/**
 * Whether somebody has already been linked to the family they came from.
 *
 * A link is always a proposal — naming the family line, or claiming her own
 * record as the same person — so the answer lives in the change requests, not
 * on the person. A spouse inherits her husband's branch when she is added, so
 * the branch column alone cannot tell "linked to her own family" from "never
 * asked".
 */
class FamilyLinkStatus
{
    /**
     * The link waiting for review, else the one in effect, else null.
     *
     * @return array{state: 'pending'|'linked', kind: 'branch'|'unlink'|'merge', label: string|null, change_request_ulid: string}|null
     */
    public function forPerson(Person $person): ?array
    {
        $requests = ChangeRequest::query()
            ->where('target_type', $person->getMorphClass())
            ->where('target_id', $person->getKey())
            ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::Approved])
            ->where(fn ($query) => $query
                ->where('operation', ChangeRequestOperation::Merge)
                ->orWhereJsonContainsKey('payload->family_branch_id'))
            ->latest('id')
            ->get();

        $pending = $requests->firstWhere('status', ChangeRequestStatus::Pending);

        if ($pending !== null) {
            return $this->describe($pending, 'pending');
        }

        $inEffect = $requests->first(fn (ChangeRequest $request) => $this->isInEffect($request, $person));

        return $inEffect === null ? null : $this->describe($inEffect, 'linked');
    }

    /**
     * What a request in the queue says about somebody's family, for a card
     * that has to offer the right thing to do next.
     *
     * @return array{kind: 'branch'|'unlink'|'merge', in_effect: bool}|null
     */
    public function forRequest(ChangeRequest $request): ?array
    {
        $kind = $this->kindOf($request);

        if ($kind === null) {
            return null;
        }

        $target = $request->relationLoaded('target') ? $request->target : null;

        return [
            'kind' => $kind,
            'in_effect' => $target instanceof Person && $this->isInEffect($request, $target),
        ];
    }

    /** @return 'branch'|'unlink'|'merge'|null */
    public function kindOf(ChangeRequest $request): ?string
    {
        if ($request->operation === ChangeRequestOperation::Merge) {
            return 'merge';
        }

        $payload = $request->payload ?? [];

        if ($request->operation !== ChangeRequestOperation::Update || ! array_key_exists('family_branch_id', $payload)) {
            return null;
        }

        return $payload['family_branch_id'] === null ? 'unlink' : 'branch';
    }

    /**
     * An approved link to the branch she is in now. A later unlink or a link
     * to somebody else's family leaves it approved but no longer true.
     */
    private function isInEffect(ChangeRequest $request, Person $person): bool
    {
        return $request->status === ChangeRequestStatus::Approved
            && $this->kindOf($request) === 'branch'
            && $person->family_branch_id !== null
            && (int) $request->payload['family_branch_id'] === (int) $person->family_branch_id;
    }

    /** @return array{state: 'pending'|'linked', kind: 'branch'|'unlink'|'merge', label: string|null, change_request_ulid: string} */
    private function describe(ChangeRequest $request, string $state): array
    {
        $kind = $this->kindOf($request) ?? 'branch';

        return [
            'state' => $state,
            'kind' => $kind,
            'label' => $kind === 'branch'
                ? FamilyBranch::whereKey($request->payload['family_branch_id'])->value('name')
                : null,
            'change_request_ulid' => $request->ulid,
        ];
    }
}
