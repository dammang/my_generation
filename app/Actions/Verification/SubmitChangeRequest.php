<?php

declare(strict_types=1);

namespace App\Actions\Verification;

use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a proposed change for review.
 *
 * `original_snapshot` is taken now, not at review time: comparing it against
 * the record's state when somebody finally approves is how concurrent edits are
 * detected without holding a lock. A record that moved in between is marked
 * superseded and the reviewer gets a three-way diff instead of a silent
 * overwrite.
 */
class SubmitChangeRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        User $requester,
        ChangeRequestOperation $operation,
        ?Model $target,
        array $payload,
        ?Scope $scope = null,
        ?string $reason = null,
        ?int $sourceId = null,
        ?string $clientOperationId = null,
        ?ChangeRequest $parent = null,
        ?string $targetType = null,
    ): ChangeRequest {
        $snapshot = null;
        $diff = null;

        if ($target !== null) {
            $snapshot = $this->snapshotOf($target, array_keys($payload));
            $diff = $this->diff($snapshot, $payload);
        }

        return ChangeRequest::create([
            'operation' => $operation,
            'target_type' => $targetType ?? $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'parent_change_request_id' => $parent?->getKey(),
            'payload' => $payload,
            'original_snapshot' => $snapshot,
            'diff' => $diff,
            'scope_id' => $scope?->getKey(),
            'reason' => $reason,
            'source_id' => $sourceId,
            'status' => ChangeRequestStatus::Pending,
            'client_operation_id' => $clientOperationId,
            'requested_by' => $requester->getKey(),
        ]);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function snapshotOf(Model $target, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $value = $target->getAttribute($field);

            $snapshot[$field] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $payload
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(array $snapshot, array $payload): array
    {
        $diff = [];

        foreach ($payload as $field => $new) {
            $old = $snapshot[$field] ?? null;

            if ($old !== $new) {
                $diff[$field] = [$old, $new];
            }
        }

        return $diff;
    }
}
