<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 *
 * A disagreement, with every claim intact. Both the 1921 and the 1923 are
 * shown: a disagreement in a family archive is itself information, and
 * resolving one by deleting the losing value destroys the evidence that the
 * question was ever open.
 */
class DisputeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'field' => $this->field,
            'label' => RevisionResource::labelFor((string) $this->field),
            'status' => $this->status->value,
            'resolution' => $this->resolution?->value,
            'resolution_note' => $this->resolution_note,
            'opened_at' => $this->created_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'opened_by' => $this->whenLoaded('openedBy', fn () => $this->openedBy === null ? null : [
                'ulid' => $this->openedBy->ulid,
                'name' => $this->openedBy->name,
            ]),
            'accepted_claim_id' => $this->accepted_claim_id,
            'claims' => $this->whenLoaded('claims', fn () => $this->claims->map(fn ($claim) => [
                'id' => $claim->id,
                'value' => $claim->claimed_value,
                'rationale' => $claim->rationale,
                'supporters' => $claim->supporter_count,
                'accepted' => $claim->id === $this->accepted_claim_id,
                'claimed_by' => $claim->relationLoaded('claimedBy') && $claim->claimedBy !== null
                    ? ['ulid' => $claim->claimedBy->ulid, 'name' => $claim->claimedBy->name]
                    : null,
            ])->all()),
        ];
    }
}
