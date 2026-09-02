<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeRequest
 *
 * A proposal, with enough for a reviewer to decide without opening the record.
 * The diff is field → [before, after]; a queue that only says "a change was
 * proposed" makes the reviewer do the work twice.
 */
class ChangeRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'operation' => $this->operation->value,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'diff' => $this->readableDiff(),
            'target' => [
                'type' => $this->target_type,
                'ulid' => $this->targetUlid(),
                'label' => $this->targetLabel(),
            ],
            'requested_by' => $this->whenLoaded('requester', fn () => [
                'ulid' => $this->requester->ulid,
                'name' => $this->requester->name,
            ]),
            'decided_by' => $this->whenLoaded('decider', fn () => $this->decider === null ? null : [
                'ulid' => $this->decider->ulid,
                'name' => $this->decider->name,
            ]),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn ($review) => [
                'decision' => $review->decision->value,
                'comment' => $review->comment,
                'at' => $review->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * Field names as a person would say them.
     *
     * `birth_date_precision` is a column name, not something to put in front of
     * somebody deciding whether a correction is right.
     *
     * @return array<int, array{field: string, label: string, before: mixed, after: mixed}>
     */
    private function readableDiff(): array
    {
        $labels = [
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'native_name' => 'Name in own script',
            'nickname' => 'Nickname',
            'display_name' => 'Display name',
            'gender' => 'Gender',
            'birth' => 'Born',
            'death' => 'Died',
            'biography' => 'Biography',
            'is_living' => 'Living',
            'privacy_level' => 'Privacy',
            'occupation' => 'Occupation',
            'notes' => 'Notes',
        ];

        $diff = [];

        foreach ($this->diff ?? [] as $field => $pair) {
            $diff[] = [
                'field' => $field,
                'label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'before' => $pair[0] ?? null,
                'after' => $pair[1] ?? null,
            ];
        }

        return $diff;
    }

    private function targetUlid(): ?string
    {
        if ($this->target_id === null) {
            return null;
        }

        return $this->relationLoaded('target') && $this->target !== null
            ? $this->target->ulid
            : null;
    }

    private function targetLabel(): ?string
    {
        if (! $this->relationLoaded('target') || $this->target === null) {
            return null;
        }

        return $this->target->display_name
            ?? $this->target->title
            ?? $this->target->name
            ?? null;
    }
}
