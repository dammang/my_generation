<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Clan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Clan
 *
 * `depth` and `level_label` travel with every clan because no fixed number of
 * hierarchy levels is assumed — one tribe's "Sub-clan" is another's "Phung",
 * and a client must render whatever the tribe actually uses.
 */
class ClanResource extends JsonResource
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
            'history' => $this->when($request->routeIs('*.show'), $this->history),
            'depth' => $this->depth,
            'level_label' => $this->level_label,
            'has_children' => $this->when(
                $this->child_clans_count !== null,
                fn () => $this->child_clans_count > 0,
            ),
            'status' => $this->status->value,
            'counts' => ['people' => $this->people_count],
            'tribe' => $this->whenLoaded('tribe', fn () => [
                'ulid' => $this->tribe->ulid,
                'name' => $this->tribe->name,
            ]),
            'parent_clan' => $this->whenLoaded('parentClan', fn () => $this->parentClan === null ? null : [
                'ulid' => $this->parentClan->ulid,
                'name' => $this->parentClan->name,
            ]),
            'children' => ClanResource::collection($this->whenLoaded('childClans')),
        ];
    }
}
