<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Membership */
class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'scope' => $this->whenLoaded('scope', fn () => [
                'type' => $this->scope->scopeable_type,
                'ulid' => $this->scope->scopeable?->ulid,
                'name' => $this->scope->scopeable?->name,
            ]),
            // Only ever exposed to somebody who administers the scope.
            'user' => $this->whenLoaded('user', fn () => [
                'ulid' => $this->user->ulid,
                'name' => $this->user->name,
            ]),
        ];
    }
}
