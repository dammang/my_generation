<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Revision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Revision
 *
 * One field changing, once. The ledger is field-level rather than row-level so
 * "who changed the birth year" has an answer — a row-level snapshot can only
 * say that somebody saved the record.
 */
class RevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field' => $this->field,
            // A row-level entry — the record being created or removed — has no
            // field. It is still history, and still belongs on the timeline.
            'label' => $this->field === null
                ? self::labelForAction($this->action->value)
                : self::labelFor($this->field),
            'action' => $this->action->value,
            'before' => $this->old_value,
            'after' => $this->new_value,
            'reason' => $this->reason,
            'at' => $this->created_at?->toIso8601String(),
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy === null ? null : [
                'ulid' => $this->changedBy->ulid,
                'name' => $this->changedBy->name,
            ]),
            // Present when the change came through review rather than directly.
            'via_change_request' => $this->change_request_id !== null,
            'source' => $this->whenLoaded('source', fn () => $this->source === null ? null : [
                'ulid' => $this->source->ulid,
                'title' => $this->source->title,
            ]),
        ];
    }

    public static function labelForAction(string $action): string
    {
        return match ($action) {
            'created' => 'Record added',
            'deleted' => 'Record removed',
            'restored' => 'Record restored',
            'merged' => 'Merged with another record',
            default => ucfirst($action),
        };
    }

    public static function labelFor(string $field): string
    {
        return match ($field) {
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'native_name' => 'Name in own script',
            'display_name' => 'Display name',
            'birth_date', 'birth_date_text', 'birth_year' => 'Born',
            'death_date', 'death_date_text', 'death_year' => 'Died',
            'is_living' => 'Living',
            'privacy_level' => 'Privacy',
            'verification_status' => 'Verification',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }
}
