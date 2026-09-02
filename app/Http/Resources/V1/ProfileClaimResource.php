<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ProfileClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProfileClaim */
class ProfileClaimResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status->value,
            'evidence' => $this->evidence,
            'relationship_statement' => $this->relationship_statement,
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'person' => PersonResource::make($this->whenLoaded('person')),
            // Only ever serialised for somebody who administers the scope.
            'claimant' => $this->whenLoaded('user', fn () => [
                'ulid' => $this->user->ulid,
                'name' => $this->user->name,
            ]),
        ];
    }
}
