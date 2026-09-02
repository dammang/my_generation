<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\AddRelative;
use App\Actions\Genealogy\CreatePerson;
use App\Actions\Verification\SubmitChangeRequest;
use App\Enums\ChangeRequestOperation;
use App\Enums\PersonNameType;
use App\Enums\RelationshipSubtype;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AddRelativeRequest;
use App\Http\Requests\V1\IndexPeopleRequest;
use App\Http\Requests\V1\StorePersonRequest;
use App\Http\Requests\V1\UpdatePersonRequest;
use App\Http\Resources\V1\PersonNameResource;
use App\Http\Resources\V1\PersonResource;
use App\Http\Resources\V1\UnionResource;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\Place;
use App\Models\Tribe;
use App\Models\Union;
use App\Policies\ResolvesScopePath;
use App\Services\Integrity\GenealogyWarnings;
use App\Services\Privacy\ViewerScope;
use App\Services\Verification\WriteGate;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * People, their names, and the guided Add Relative flow.
 *
 * A write either lands directly or becomes a change request, depending on
 * whether the record is verified and what the contributor may do in its scope.
 * Both are successful outcomes: 201 for a direct write, 202 for a proposal.
 */
class PersonController extends Controller
{
    use ResolvesScopePath;

    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly WriteGate $gate,
        private readonly GenealogyWarnings $warnings,
    ) {}

    public function index(IndexPeopleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Person::class);

        $people = Person::query()
            // The predicate goes in the WHERE clause. Filtering after
            // pagination produces short pages and leaks total counts.
            ->visibleTo($this->viewer)
            ->notMerged()
            ->inGraph()
            ->with(['tribe:id,ulid,name', 'clan:id,ulid,name', 'familyBranch:id,ulid,name', 'profileMedia'])
            ->when($request->filled('q'), fn (Builder $q) => $q->where(
                fn (Builder $q) => $q
                    ->where('display_name', 'like', $request->string('q').'%')
                    ->orWhere('sort_name', 'like', $request->string('q')->lower().'%')
            ))
            ->when($request->filled('tribe'), fn (Builder $q) => $q->where(
                'tribe_id',
                Tribe::where('ulid', $request->string('tribe'))->value('id')
            ))
            ->when($request->filled('clan'), fn (Builder $q) => $q->where(
                'clan_id',
                Clan::where('ulid', $request->string('clan'))->value('id')
            ))
            ->when($request->filled('branch'), fn (Builder $q) => $q->where(
                'family_branch_id',
                FamilyBranch::where('ulid', $request->string('branch'))->value('id')
            ))
            ->when($request->has('living'), fn (Builder $q) => $q->where('is_living', $request->boolean('living')))
            ->orderBy('sort_name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return ApiResponse::success(PersonResource::collection($people));
    }

    /**
     * Route binding already applied the privacy predicate, so an unauthorised
     * record arrives here as a 404 rather than reaching the policy at all.
     */
    public function show(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $person->load([
            'tribe:id,ulid,name',
            'clan:id,ulid,name',
            'familyBranch:id,ulid,name',
            'generation',
            'birthPlace',
            'deathPlace',
            'profileMedia',
            'mergedInto:id,ulid',
        ]);

        return ApiResponse::success(PersonResource::make($person));
    }

    public function store(StorePersonRequest $request, CreatePerson $action): JsonResponse
    {
        $attributes = $this->mapAttributes($request->validated());

        $person = $action->handle($request->user(), $attributes);

        return ApiResponse::created(
            PersonResource::make($person->load(['tribe:id,ulid,name', 'clan:id,ulid,name'])),
            warnings: array_map(
                fn ($w) => $w->jsonSerialize(),
                $this->warnings->forPerson($person),
            ),
        );
    }

    public function update(
        UpdatePersonRequest $request,
        Person $person,
        SubmitChangeRequest $propose,
    ): JsonResponse {
        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $attributes = $this->mapAttributes($data);

        // Verified genealogy is never silently overwritten. Without verify
        // permission in this person's scope, the edit becomes a proposal.
        if (! $this->gate->isDirect($request->user(), $person, 'people', $this->scopePathFor($person))) {
            $changeRequest = $propose->handle(
                requester: $request->user(),
                operation: ChangeRequestOperation::Update,
                target: $person,
                payload: $attributes,
                // Filed against the person's own scope, so a clan reviewer's
                // queue contains their clan's proposals.
                scope: $this->scopeFor($person),
                reason: $reason,
            );

            return ApiResponse::accepted([
                'person' => null,
                'change_request' => [
                    'ulid' => $changeRequest->ulid,
                    'status' => $changeRequest->status->value,
                    'diff' => $changeRequest->diff,
                ],
            ]);
        }

        $person->withRevisionContext(reason: $reason);
        $person->fill(collect($attributes)->except(['birth', 'death'])->all());

        foreach (['birth', 'death'] as $prefix) {
            if (array_key_exists($prefix, $attributes)) {
                $person->setUncertainDate($prefix, $attributes[$prefix]);
            }
        }

        $person->save();

        return ApiResponse::success(
            PersonResource::make($person->fresh(['tribe:id,ulid,name', 'clan:id,ulid,name'])),
            warnings: array_map(fn ($w) => $w->jsonSerialize(), $this->warnings->forPerson($person)),
        );
    }

    public function destroy(Person $person): JsonResponse
    {
        $this->authorize('delete', $person);

        // Soft delete: the record leaves the graph, the history does not.
        $person->delete();

        return ApiResponse::noContent();
    }

    /**
     * Parents, spouses, children and siblings in one call — the person profile
     * needs all four, and four round trips on a phone is three too many.
     */
    /**
     * Marks a record as checked by somebody entitled to say so.
     *
     * Verification locks the record against direct edits: from here anyone
     * without verify permission in this scope proposes rather than overwrites.
     * That is the whole point of the status — it is a gate, not a badge.
     */
    public function verify(Request $request, Person $person): JsonResponse
    {
        $this->authorize('verify', $person);

        $data = $request->validate([
            'verified' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $verifying = $data['verified'] ?? true;

        $person->withRevisionContext(reason: $data['note'] ?? null);

        $person->forceFill($verifying
            ? [
                'verification_status' => VerificationStatus::Verified,
                'verified_by' => $request->user()->getKey(),
                'verified_at' => now(),
            ]
            : [
                'verification_status' => VerificationStatus::Unverified,
                'verified_by' => null,
                'verified_at' => null,
            ])->save();

        return ApiResponse::success(
            PersonResource::make($person->fresh(['tribe:id,ulid,name', 'clan:id,ulid,name']))
        );
    }

    public function family(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $with = ['tribe:id,ulid,name', 'clan:id,ulid,name', 'profileMedia'];

        return ApiResponse::success([
            'person' => PersonResource::make($person->load($with)),
            'parents' => PersonResource::collection(
                $person->parents()->with($with)->get()
            ),
            'spouses' => PersonResource::collection(
                Person::whereIn('id', $person->spouses()->pluck('id'))->with($with)->get()
            ),
            'children' => PersonResource::collection(
                $person->children()->with($with)->get()
            ),
            'siblings' => PersonResource::collection(
                Person::whereIn('id', $person->siblings()->pluck('id'))->with($with)->get()
            ),
            'unions' => UnionResource::collection(
                Union::involving($person->getKey())
                    ->with(['partner1', 'partner2', 'children'])
                    ->orderBy('order_index')
                    ->get()
            ),
        ]);
    }

    /**
     * The guided flow: a contributor picks "Add Son" and never learns that a
     * union row exists.
     */
    public function addRelative(AddRelativeRequest $request, Person $person, AddRelative $action): JsonResponse
    {
        $data = $request->validated();

        $outcome = $action->handle(
            author: $request->user(),
            anchor: $person,
            relation: $data['relation'],
            attributes: $this->mapAttributes($data['person']),
            union: isset($data['union_ulid'])
                ? Union::where('ulid', $data['union_ulid'])->first()
                : null,
            subtype: isset($data['relationship_subtype'])
                ? RelationshipSubtype::from($data['relationship_subtype'])
                : RelationshipSubtype::Biological,
            customLabel: $data['custom_label'] ?? null,
        );

        return ApiResponse::success(
            [
                'person' => PersonResource::make(
                    $outcome->record->load(['tribe:id,ulid,name', 'clan:id,ulid,name'])
                )->resolve(),
                'created' => $outcome->created,
                'change_request' => null,
            ],
            warnings: $outcome->warningPayload(),
            status: $outcome->status(),
        );
    }

    public function names(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        return ApiResponse::success(
            PersonNameResource::collection($person->names()->orderByDesc('is_primary')->get())
        );
    }

    /**
     * Alternate spellings are how "Thawng Dam" and "Thawngdam" resolve to one
     * ancestor, so adding them is a first-class action rather than an edit to
     * a hidden field.
     */
    public function storeName(Request $request, Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'type' => ['sometimes', Rule::enum(PersonNameType::class)],
            'script' => ['sometimes', 'nullable', 'string', 'max:20'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $name = PersonName::firstOrCreate(
            [
                'person_id' => $person->getKey(),
                'normalized' => mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $data['name']) ?? $data['name']),
                'type' => $data['type'] ?? PersonNameType::Alternate->value,
            ],
            [
                'name' => $data['name'],
                'script' => $data['script'] ?? null,
                'language' => $data['language'] ?? null,
                'created_by' => $request->user()->getKey(),
            ],
        );

        return ApiResponse::created(PersonNameResource::make($name));
    }

    public function destroyName(Person $person, PersonName $personName): JsonResponse
    {
        $this->authorize('update', $person);

        if ($personName->person_id !== $person->getKey()) {
            return ApiResponse::error('That name does not belong to this person.', 404);
        }

        if ($personName->is_primary) {
            return ApiResponse::error(
                'The primary name cannot be removed. Edit the person instead.',
                422,
                [],
                'PRIMARY_NAME_PROTECTED',
            );
        }

        $personName->delete();

        return ApiResponse::noContent();
    }

    /**
     * Client-facing ULIDs become foreign keys here, in one place, so no
     * controller action has to remember the mapping.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapAttributes(array $data): array
    {
        $map = [
            'birth_place_ulid' => ['birth_place_id', Place::class],
            'death_place_ulid' => ['death_place_id', Place::class],
            'tribe_ulid' => ['tribe_id', Tribe::class],
            'clan_ulid' => ['clan_id', Clan::class],
            'family_branch_ulid' => ['family_branch_id', FamilyBranch::class],
        ];

        foreach ($map as $input => [$column, $model]) {
            if (array_key_exists($input, $data)) {
                $data[$column] = $data[$input] === null
                    ? null
                    : $model::where('ulid', $data[$input])->value('id');
                unset($data[$input]);
            }
        }

        return $data;
    }
}
