<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Tribe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tribe */
class TribeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'native_name' => $this->native_name,
            'short_name' => $this->short_name,
            'description' => $this->description,
            'history' => $this->when($request->routeIs('*.show'), $this->history),
            'country_code' => $this->country_code,
            'region' => $this->region,
            'default_privacy_level' => $this->default_privacy_level->value,
            'status' => $this->status->value,
            'counts' => [
                'people' => $this->people_count,
                'clans' => $this->clan_count,
            ],
            'clans' => ClanResource::collection($this->whenLoaded('rootClans')),
            'generations' => GenerationResource::collection($this->whenLoaded('generations')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
