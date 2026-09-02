<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\PersonName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PersonName */
class PersonNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'type' => $this->type->value,
            'script' => $this->script,
            'language' => $this->language,
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
