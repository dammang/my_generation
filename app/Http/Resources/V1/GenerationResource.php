<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'generation_number' => $this->generation_number,
            'generation_name' => $this->generation_name,
            'local_name' => $this->local_name,
            'description' => $this->description,
            'estimated_start_year' => $this->estimated_start_year,
            'estimated_end_year' => $this->estimated_end_year,
        ];
    }
}
