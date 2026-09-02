<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexPeopleRequest;
use App\Http\Resources\V1\PersonResource;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Tribe;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Read-only for now. Person writes, and the Add Relative flow, arrive with the
 * genealogy phase; these two endpoints exist so privacy enforcement is provable
 * over HTTP rather than only in unit tests.
 */
class PersonController extends Controller
{
    public function __construct(private readonly ViewerScope $viewer) {}

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
}
