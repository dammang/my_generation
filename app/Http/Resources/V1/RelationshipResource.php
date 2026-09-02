<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Relationship
 *
 * `person` is always the parent, guardian or asserted elder. Direction is
 * canonical in the schema and stays canonical on the wire, so a client never
 * has to guess which end is which.
 */
class RelationshipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->relationship_type->value,
            'subtype' => $this->relationship_subtype->value,
            'custom_label' => $this->custom_label,
            'is_biological' => $this->is_biological,
            'certainty' => $this->certainty->value,
            'verification_status' => $this->verification_status->value,
            'notes' => $this->notes,
            'person' => PersonResource::make($this->whenLoaded('person')),
            'related_person' => PersonResource::make($this->whenLoaded('relatedPerson')),
            'union_ulid' => $this->whenLoaded('union', fn () => $this->union?->ulid),
        ];
    }
}
