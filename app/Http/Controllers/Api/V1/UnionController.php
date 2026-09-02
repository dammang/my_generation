<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\AddChildToUnion;
use App\Enums\ChildRelationshipType;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreUnionRequest;
use App\Http\Resources\V1\UnionResource;
use App\Models\Person;
use App\Models\Place;
use App\Models\Relationship;
use App\Models\Union;
use App\Models\UnionChild;
use App\Services\Integrity\GenealogyWarnings;
use App\Services\Privacy\ViewerScope;
use App\Services\Statistics\ContributionCounter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnionController extends Controller
{
    public function __construct(private readonly GenealogyWarnings $warnings) {}

    public function store(
        StoreUnionRequest $request,
        ContributionCounter $contributions,
    ): JsonResponse {
        $data = $request->validated();

        $partner1 = $this->visiblePerson($data['partner_1_ulid']);
        $partner2 = isset($data['partner_2_ulid']) ? $this->visiblePerson($data['partner_2_ulid']) : null;

        $union = DB::transaction(function () use ($data, $partner1, $partner2): Union {
            // Partner order is normalised by the observer, so the unique key
            // catches the same marriage entered from either spouse's screen.
            $union = new Union([
                'partner_1_id' => $partner1->getKey(),
                'partner_2_id' => $partner2?->getKey(),
                'union_type' => isset($data['union_type'])
                    ? UnionType::from($data['union_type'])
                    : UnionType::Marriage,
                'status' => isset($data['status']) ? UnionStatus::from($data['status']) : UnionStatus::Unknown,
                'separation_date' => $data['separation_date'] ?? null,
                'divorce_date' => $data['divorce_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'marriage_place_id' => isset($data['marriage_place_ulid'])
                    ? Place::where('ulid', $data['marriage_place_ulid'])->value('id')
                    : null,
            ]);

            if (isset($data['marriage'])) {
                $union->setUncertainDate('marriage', $data['marriage']);
            }

            $union->save();

            return $union;
        });

        $contributions->increment($request->user(), 'unions_added');

        return ApiResponse::created(
            UnionResource::make($union->load(['partner1', 'partner2', 'marriagePlace'])),
            warnings: array_map(
                fn ($w) => $w->jsonSerialize(),
                $this->warnings->forUnion($union, $partner1, $partner2),
            ),
        );
    }

    public function show(Union $union): JsonResponse
    {
        $union->load(['partner1', 'partner2', 'children', 'marriagePlace']);

        return ApiResponse::success(UnionResource::make($union));
    }

    public function update(Request $request, Union $union): JsonResponse
    {
        $this->authorize('update', $union);

        $data = $request->validate([
            'union_type' => ['sometimes', Rule::enum(UnionType::class)],
            'status' => ['sometimes', Rule::enum(UnionStatus::class)],
            'marriage' => ['sometimes', 'nullable', 'string', 'max:120'],
            'separation_date' => ['sometimes', 'nullable', 'date'],
            'divorce_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $reason = $data['reason'] ?? null;
        $marriage = $data['marriage'] ?? null;
        unset($data['reason'], $data['marriage']);

        $union->withRevisionContext(reason: $reason)->fill($data);

        if (array_key_exists('marriage', $request->all())) {
            $union->setUncertainDate('marriage', $marriage);
        }

        $union->save();

        return ApiResponse::success(
            UnionResource::make($union->fresh(['partner1', 'partner2'])),
            warnings: array_map(
                fn ($w) => $w->jsonSerialize(),
                $this->warnings->forUnion($union, $union->partner1, $union->partner2),
            ),
        );
    }

    public function destroy(Union $union): JsonResponse
    {
        $this->authorize('delete', $union);

        $union->delete();

        return ApiResponse::noContent();
    }

    /** Adds an existing person as a child of this couple. */
    public function addChild(Request $request, Union $union, AddChildToUnion $action): JsonResponse
    {
        $this->authorize('update', $union);

        $data = $request->validate([
            'person_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'relationship_type' => ['sometimes', Rule::enum(ChildRelationshipType::class)],
            'birth_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $child = $this->visiblePerson($data['person_ulid']);

        $warnings = $action->handle(
            union: $union,
            child: $child,
            kind: isset($data['relationship_type'])
                ? ChildRelationshipType::from($data['relationship_type'])
                : ChildRelationshipType::Biological,
            birthOrder: $data['birth_order'] ?? null,
        );

        return ApiResponse::created(
            UnionResource::make($union->fresh(['partner1', 'partner2', 'children'])),
            warnings: array_map(fn ($w) => $w->jsonSerialize(), $warnings),
        );
    }

    /**
     * Removing a child detaches the grouping row and the parent edges it
     * created together — leaving the edges behind would show a child with
     * parents but no place on the chart.
     */
    public function removeChild(Union $union, Person $person): JsonResponse
    {
        $this->authorize('update', $union);

        DB::transaction(function () use ($union, $person): void {
            UnionChild::where('union_id', $union->getKey())
                ->where('person_id', $person->getKey())
                ->delete();

            Relationship::where('union_id', $union->getKey())
                ->where('related_person_id', $person->getKey())
                ->get()
                ->each(fn ($relationship) => $relationship->delete());
        });

        return ApiResponse::noContent();
    }

    private function visiblePerson(string $ulid): Person
    {
        return Person::where('ulid', $ulid)
            ->visibleTo(app(ViewerScope::class))
            ->firstOrFail();
    }
}
