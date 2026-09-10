<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class NoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'body' => $this->body,
            'audience' => $this->audience->value,
            'audience_label' => $this->audience->label(),
            'written_at' => $this->created_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'ulid' => $this->author->ulid,
                'name' => $this->author->name,
            ]),
            // So the app can offer to remove it without guessing.
            'mine' => $request->user()?->getKey() === $this->author_id,
        ];
    }
}
