<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\CreateRelationship;
use App\Enums\Certainty;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreRelationshipRequest;
use App\Http\Resources\V1\RelationshipResource;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Union;
use App\Services\Privacy\ViewerScope;
use App\Services\Statistics\ContributionCounter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RelationshipController extends Controller
{
    public function store(
        StoreRelationshipRequest $request,
        CreateRelationship $action,
        ContributionCounter $contributions,
    ): JsonResponse {
        $data = $request->validated();

        // Route binding is not in play here, so the privacy predicate has to be
        // applied by hand: a contributor must not be able to attach an edge to
        // a person they cannot see, which would otherwise confirm they exist.
        $from = $this->visiblePerson($data['person_ulid']);
        $to = $this->visiblePerson($data['related_person_ulid']);

        [$relationship, $warnings] = $action->handle(
            from: $from,
            to: $to,
            type: isset($data['relationship_type'])
                ? RelationshipType::from($data['relationship_type'])
                : RelationshipType::ParentChild,
            subtype: isset($data['relationship_subtype'])
                ? RelationshipSubtype::from($data['relationship_subtype'])
                : RelationshipSubtype::Biological,
            union: isset($data['union_ulid']) ? Union::where('ulid', $data['union_ulid'])->first() : null,
            certainty: isset($data['certainty']) ? Certainty::from($data['certainty']) : Certainty::Probable,
            customLabel: $data['custom_label'] ?? null,
        );

        if (isset($data['notes'])) {
            $relationship->update(['notes' => $data['notes']]);
        }

        $contributions->increment($request->user(), 'relationships_added');

        return ApiResponse::created(
            RelationshipResource::make($relationship->load(['person', 'relatedPerson'])),
            warnings: array_map(fn ($w) => $w->jsonSerialize(), $warnings),
        );
    }

    public function update(Request $request, Relationship $relationship): JsonResponse
    {
        $this->authorize('update', $relationship);

        $data = $request->validate([
            'relationship_subtype' => ['sometimes', Rule::enum(RelationshipSubtype::class)],
            'certainty' => ['sometimes', Rule::enum(Certainty::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $relationship->withRevisionContext(reason: $reason)->update($data);

        return ApiResponse::success(
            RelationshipResource::make($relationship->fresh(['person', 'relatedPerson']))
        );
    }

    public function destroy(Relationship $relationship): JsonResponse
    {
        $this->authorize('delete', $relationship);

        // The row survives soft-deleted for the audit trail; the observer
        // retracts its edge so it leaves the graph immediately.
        $relationship->delete();

        return ApiResponse::noContent();
    }

    private function visiblePerson(string $ulid): Person
    {
        return Person::where('ulid', $ulid)
            ->visibleTo(app(ViewerScope::class))
            ->firstOrFail();
    }
}
