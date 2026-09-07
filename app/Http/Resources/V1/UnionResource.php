<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Union;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Union */
class UnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'union_type' => $this->union_type->value,
            'status' => $this->status->value,
            'order_index' => $this->order_index,
            'marriage' => $this->dateFact('marriage')->isKnown()
                ? [
                    'year' => $this->marriage_year,
                    'display' => $this->dateFact('marriage')->display(),
                    'precision' => $this->marriage_date_precision->value,
                ]
                : null,
            'separation_date' => $this->separation_date?->toDateString(),
            'divorce_date' => $this->divorce_date?->toDateString(),
            'children_count' => $this->children_count,
            'verification_status' => $this->verification_status->value,
            'marriage_place' => PlaceResource::make($this->whenLoaded('marriagePlace')),
            'partners' => $this->when(
                $this->relationLoaded('partner1'),
                fn () => array_values(array_filter([
                    $this->partner1 === null ? null : PersonResource::make($this->partner1)->resolve(),
                    $this->partner2 === null ? null : PersonResource::make($this->partner2)->resolve(),
                ])),
            ),
            // Each child with their pivot: birth order is what a family
            // actually names them by — "the second son" — and adoption is a
            // fact about the child's place here, not about the child.
            'children' => $this->whenLoaded('children', fn () => $this->children
                ->map(fn ($child) => [
                    ...PersonResource::make($child)->resolve(),
                    'birth_order' => $child->pivot?->birth_order,
                    'relationship_type' => $child->pivot?->relationship_type,
                ])
                ->all()),
        ];
    }
}
