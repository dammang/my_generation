<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Media;
use App\Services\Media\MediaUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 *
 * The stored path never leaves the server. A client gets a URL that either
 * points at the public domain or is signed and expiring, and nothing it could
 * use to construct a second one for itself.
 */
class MediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_filter([
            'ulid' => $this->ulid,
            'collection' => $this->collection->value,
            'url' => app(MediaUrlResolver::class)->url($this->resource),
            'is_private' => $this->is_private,
            'caption' => $this->caption,
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $this->size_bytes,
            'taken_at' => $this->taken_at?->toDateString(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
        ], fn ($value) => $value !== null);
    }
}
