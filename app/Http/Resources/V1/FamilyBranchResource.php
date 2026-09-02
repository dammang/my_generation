<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\FamilyBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyBranch */
class FamilyBranchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'native_name' => $this->native_name,
            'description' => $this->description,
            'current_region' => $this->current_region,
            'status' => $this->status->value,
            'counts' => [
                'people' => $this->people_count,
                'generations' => $this->generation_count,
            ],
            // The apical ancestor generations are counted from. Serialised
            // through PersonResource so it obeys the same privacy mask as
            // anywhere else a person appears.
            'ancestor' => $this->whenLoaded('ancestor', fn () => $this->ancestor === null
                ? null
                : PersonResource::make($this->ancestor)),
            'origin_place' => PlaceResource::make($this->whenLoaded('originPlace')),
            'tribe' => $this->whenLoaded('tribe', fn () => [
                'ulid' => $this->tribe->ulid,
                'name' => $this->tribe->name,
            ]),
            'clan' => $this->whenLoaded('clan', fn () => $this->clan === null ? null : [
                'ulid' => $this->clan->ulid,
                'name' => $this->clan->name,
            ]),
        ];
    }
}
