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
 */
class StoryResource extends JsonResource
{
    public function __construct($resource, private readonly bool $withBody = false)
    {
        parent::__construct($resource);
    }

    /** A single story, body included. */
    public static function full(Story $story): self
    {
        return new self($story, withBody: true);
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
