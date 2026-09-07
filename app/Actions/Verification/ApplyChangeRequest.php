<?php

declare(strict_types=1);

namespace App\Actions\Verification;

use App\Actions\Merge\MergePeople;
use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Enums\ReviewDecision;
use App\Enums\VerificationStatus;
use App\Exceptions\ChangeRequestSupersededException;
use App\Exceptions\GenealogyRuleException;
use App\Models\ChangeRequest;
use App\Models\Citation;
use App\Models\Person;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Statistics\ContributionCounter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Applies an approved proposal to its target.
 *
 * Two things happen here that cannot happen at review time:
 *
 *   The reviewer's permission is re-checked *now*. A role revoked between
 *   opening the queue and clicking approve must take effect.
 *
 *   The record's current state is compared against the snapshot taken when the
 *   proposal was filed. A record that moved in between is marked superseded and
 *   the reviewer gets the conflict, rather than silently overwriting whatever
 *   somebody else corrected. This is how concurrent edits are handled without
 *   holding a lock across a human decision.
 */
class ApplyChangeRequest
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ContributionCounter $contributions,
        private readonly MergePeople $merge,
    ) {}

    public function handle(ChangeRequest $request, User $reviewer, ?string $comment = null): Model
    {
        if ($request->status !== ChangeRequestStatus::Pending) {
            throw new GenealogyRuleException(
                'This request has already been decided.',
                'CHANGE_REQUEST_DECIDED',
            );
        }

        $target = $this->resolveTarget($request);

        $this->assertMayReview($request, $reviewer);
        $this->assertNotSuperseded($request, $target);

        if ($request->operation === ChangeRequestOperation::Merge) {
            return $this->applyMerge($request, $reviewer, $target, $comment);
        }

        return DB::transaction(function () use ($request, $reviewer, $target, $comment): Model {
            $payload = $request->payload ?? [];

            $target->withRevisionContext(
                reason: $request->reason,
                sourceId: $request->source_id,
                changeRequestId: $request->getKey(),
            );

            foreach ($payload as $field => $value) {
                $target->setAttribute($field, $value);
            }

            // Approving is itself an act of verification when the reviewer is
            // entitled to make one.
            if ($this->canVerify($request, $reviewer) && $target->isFillable('verification_status')) {
                $target->setAttribute('verification_status', VerificationStatus::Verified);
                $target->setAttribute('verified_by', $reviewer->getKey());
                $target->setAttribute('verified_at', now());
            }

            $target->save();

            // The evidence offered with the proposal becomes a citation on the
            // record, so the reason a fact is trusted survives the review.
            if ($request->source_id !== null) {
                Citation::firstOrCreate([
                    'source_id' => $request->source_id,
                    'citable_type' => $target->getMorphClass(),
                    'citable_id' => $target->getKey(),
                    'field' => array_key_first($payload),
                ], ['created_by' => $reviewer->getKey()]);
            }

            $request->forceFill([
                'status' => ChangeRequestStatus::Approved,
                'decided_by' => $reviewer->getKey(),
                'decided_at' => now(),
                'applied_at' => now(),
                'applied_revision_ids' => DB::table('revisions')
                    ->where('change_request_id', $request->getKey())
                    ->pluck('id')
                    ->all(),
            ])->save();

            $request->reviews()->create([
                'reviewer_id' => $reviewer->getKey(),
                'decision' => ReviewDecision::Approve,
                'comment' => $comment,
            ]);

            if ($request->requested_by !== null) {
                $this->contributions->increment(
                    User::findOrFail($request->requested_by),
                    'changes_approved',
                );
            }

            return $target;
        });
    }

    /**
     * "This spouse and that daughter are the same woman."
     *
     * A merge is not a field to set, so it cannot go down the ordinary path:
     * two records become one and everything pointing at either has to be
     * repointed. MergePeople does that reversibly and is the only thing
     * allowed to; this decides that it may happen and records who said so.
     */
    private function applyMerge(
        ChangeRequest $request,
        User $reviewer,
        Model $target,
        ?string $comment,
    ): Model {
        $otherUlid = $request->payload['merge_with_ulid'] ?? null;
        $other = $otherUlid === null ? null : Person::where('ulid', $otherUlid)->first();

        if (! $target instanceof Person || $other === null) {
            throw new GenealogyRuleException(
                'The record this was to be merged with is no longer here.',
                'MERGE_TARGET_MISSING',
            );
        }

        return DB::transaction(function () use ($request, $reviewer, $target, $other, $comment): Model {
            // The record with a family of its own wins: it carries the parents
            // and siblings, which is the whole reason for the link. The other
            // survives soft-deleted with a pointer, so an old link to it still
            // resolves rather than 404ing.
            $this->merge->handle($reviewer, $other, $target);

            $request->forceFill([
                'status' => ChangeRequestStatus::Approved,
                'decided_by' => $reviewer->getKey(),
                'decided_at' => now(),
                'applied_at' => now(),
            ])->save();

            $request->reviews()->create([
                'reviewer_id' => $reviewer->getKey(),
                'decision' => ReviewDecision::Approve,
                'comment' => $comment,
            ]);

            if ($request->requested_by !== null) {
                $this->contributions->increment(
                    User::findOrFail($request->requested_by),
                    'changes_approved',
                );
            }

            return $other->refresh();
        });
    }

    public function reject(ChangeRequest $request, User $reviewer, ?string $comment = null): ChangeRequest
    {
        $this->assertMayReview($request, $reviewer);

        return DB::transaction(function () use ($request, $reviewer, $comment): ChangeRequest {
            $request->forceFill([
                'status' => ChangeRequestStatus::Rejected,
                'decided_by' => $reviewer->getKey(),
                'decided_at' => now(),
            ])->save();

            $request->reviews()->create([
                'reviewer_id' => $reviewer->getKey(),
                'decision' => ReviewDecision::Reject,
                'comment' => $comment,
            ]);

            if ($request->requested_by !== null) {
                $this->contributions->increment(
                    User::findOrFail($request->requested_by),
                    'changes_rejected',
                );
            }

            return $request;
        });
    }

    private function resolveTarget(ChangeRequest $request): Model
    {
        $applicable = [
            ChangeRequestOperation::Update,
            ChangeRequestOperation::Merge,
        ];

        if (! in_array($request->operation, $applicable, true) || $request->target_id === null) {
            throw new GenealogyRuleException(
                'Only update and merge proposals can be applied automatically yet.',
                'CHANGE_REQUEST_UNSUPPORTED',
            );
        }

        $class = Relation::getMorphedModel($request->target_type)
            ?? $request->target_type;

        return $class::query()->findOrFail($request->target_id);
    }

    private function assertMayReview(ChangeRequest $request, User $reviewer): void
    {
        $scopePath = $request->scope_id === null
            ? null
            : DB::table('scopes')->where('id', $request->scope_id)->value('path');

        $allowed = $reviewer->is_super_admin
            || $this->permissions->can($reviewer, 'changes.approve', $scopePath);

        if (! $allowed) {
            throw new AuthorizationException(
                'You may not review changes in this scope.'
            );
        }
    }

    private function canVerify(ChangeRequest $request, User $reviewer): bool
    {
        $scopePath = $request->scope_id === null
            ? null
            : DB::table('scopes')->where('id', $request->scope_id)->value('path');

        return $reviewer->is_super_admin
            || $this->permissions->can($reviewer, 'people.verify', $scopePath);
    }

    private function assertNotSuperseded(ChangeRequest $request, Model $target): void
    {
        $snapshot = $request->original_snapshot ?? [];
        $conflicts = [];

        foreach ($snapshot as $field => $wasValue) {
            $current = $target->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value
                : ($current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current);

            if ($current != $wasValue) {
                $conflicts[$field] = [$wasValue, $current];
            }
        }

        if ($conflicts === []) {
            return;
        }

        $request->forceFill(['status' => ChangeRequestStatus::Superseded])->save();

        throw new ChangeRequestSupersededException($conflicts);
    }
}
