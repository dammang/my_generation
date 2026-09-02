<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\PersonEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonEvent
 *
 * A timeline entry. The date is rendered as the source wrote it — "abt. 1902"
 * is evidence, and reformatting it to 1902 quietly upgrades a guess to a fact.
 */
class PersonEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $fact = $this->dateFact('event');

        return [
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'year' => $this->event_year,
            'date_display' => $fact->display(),
            'date_precision' => $this->event_date_precision->value,
            'verification_status' => $this->verification_status->value,
            'type' => $this->whenLoaded('eventType', fn () => [
                'slug' => $this->eventType->slug,
                'label' => $this->eventType->label,
                'category' => $this->eventType->category->value,
                'icon' => $this->eventType->icon,
            ]),
            'place' => PlaceResource::make($this->whenLoaded('place')),
            // Migration carries both ends of the move, which is what makes a
            // family's journey readable as a journey.
            'from_place' => PlaceResource::make($this->whenLoaded('fromPlace')),
            'to_place' => PlaceResource::make($this->whenLoaded('toPlace')),
        ];
    }
}
