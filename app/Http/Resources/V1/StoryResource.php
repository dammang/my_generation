<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Story
 *
 * A family narrative. The body is only sent when a single story was asked
 * for — a listing carries the summary, because a screen showing twenty
 * stories has no use for twenty full texts and every reason not to move them.
 *
 * Whether to include it is a property set after construction, never a second
 * constructor argument. Collection::mapInto() builds resources as
 * `new static($item, $key)`, so a second parameter silently receives the
 * collection index: every story after the first would be constructed with a
 * truthy value and send its whole body. That is exactly what happened, and
 * the listing test did not catch it because it only looked at the first item —
 * the one index that is falsy.
 */
class StoryResource extends JsonResource
{
    private bool $withBody = false;

    /** A single story, body included. */
    public static function full(Story $story): self
    {
        $resource = new self($story);
        $resource->withBody = true;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_filter([
            'ulid' => $this->ulid,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->withBody ? $this->body : null,
            'story_type' => $this->story_type->value,
            'visibility' => $this->visibility->value,
            'verification_status' => $this->verification_status->value,
            'era_start_year' => $this->era_start_year,
            'era_end_year' => $this->era_end_year,
            'author' => $this->whenLoaded('author', fn () => [
                'ulid' => $this->author->ulid,
                'name' => $this->author->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => [
                'ulid' => $this->subject->ulid,
                'display_name' => $this->subject->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ], fn ($value) => $value !== null);
    }
}
