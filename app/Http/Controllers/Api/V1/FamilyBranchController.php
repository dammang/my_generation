<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\AnchorFamilyBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreFamilyBranchRequest;
use App\Http\Requests\V1\UpdateFamilyBranchRequest;
use App\Http\Resources\V1\FamilyBranchResource;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Place;
use App\Models\Tribe;
use App\Models\User;
use App\Policies\ResolvesScopePath;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyBranchController extends Controller
{
    use ResolvesScopePath;

    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PermissionResolver $permissions,
        private readonly AnchorFamilyBranch $anchor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $branches = FamilyBranch::query()
            ->visibleTo($this->viewer)
            ->when($request->filled('tribe'), fn (Builder $q) => $q->where(
                'tribe_id',
                Tribe::where('ulid', $request->string('tribe'))->value('id')
            ))
            ->when($request->filled('clan'), fn (Builder $q) => $q->where(
                'clan_id',
                Clan::where('ulid', $request->string('clan'))->value('id')
            ))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', $request->string('q').'%'))
            // The apical ancestor is what actually tells two families with
            // the same name apart, so the list that asks somebody to choose
            // between them carries it.
            ->with(['tribe:id,ulid,name', 'clan:id,ulid,name', 'ancestor'])
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return ApiResponse::success(FamilyBranchResource::collection($branches));
    }

    public function store(StoreFamilyBranchRequest $request): JsonResponse
    {
        $data = $request->validated();

        $placement = [
            'tribe_id' => Tribe::where('ulid', $data['tribe_ulid'])->value('id'),
            'clan_id' => $this->idFor(Clan::class, $data['clan_ulid'] ?? null),
        ];

        $this->assertMayManageIn($request->user(), $placement);

        $branch = FamilyBranch::create([
            ...collect($data)->except([
                'tribe_ulid', 'clan_ulid', 'ancestor_person_ulid', 'origin_place_ulid',
            ])->all(),
            ...$placement,
            'ancestor_person_id' => $this->idFor(Person::class, $data['ancestor_person_ulid'] ?? null),
            'origin_place_id' => $this->idFor(Place::class, $data['origin_place_ulid'] ?? null),
        ]);

        $placed = $this->recomputeDepths($branch);

        return ApiResponse::created(
            FamilyBranchResource::make($branch->load(['tribe:id,ulid,name', 'clan:id,ulid,name', 'ancestor'])),
            // What actually happened to the archive, so a client can say
            // "104 people are now counted from here" rather than reporting
            // that a row was written.
            meta: ['people_placed' => $placed],
        );
    }

    public function show(FamilyBranch $familyBranch): JsonResponse
    {
        $familyBranch->load(['tribe:id,ulid,name', 'clan:id,ulid,name', 'ancestor', 'originPlace']);

        return ApiResponse::success(FamilyBranchResource::make($familyBranch));
    }

    public function update(UpdateFamilyBranchRequest $request, FamilyBranch $familyBranch): JsonResponse
    {
        $data = $request->validated();

        foreach ([
            'clan_ulid' => ['clan_id', Clan::class],
            'ancestor_person_ulid' => ['ancestor_person_id', Person::class],
            'origin_place_ulid' => ['origin_place_id', Place::class],
        ] as $input => [$column, $model]) {
            if (array_key_exists($input, $data)) {
                $data[$column] = $this->idFor($model, $data[$input]);
                unset($data[$input]);
            }
        }

        $familyBranch->update($data);
        $placed = $this->recomputeDepths($familyBranch);

        return ApiResponse::success(
            FamilyBranchResource::make($familyBranch->fresh(['tribe:id,ulid,name', 'clan:id,ulid,name', 'ancestor'])),
            meta: ['people_placed' => $placed],
        );
    }

    public function destroy(FamilyBranch $familyBranch): JsonResponse
    {
        $this->authorize('delete', $familyBranch);

        if ($familyBranch->people()->exists()) {
            return ApiResponse::error(
                'This family branch still has people. Move or remove them first.',
                409,
                [],
                'SCOPE_NOT_EMPTY',
            );
        }

        $familyBranch->delete();

        return ApiResponse::noContent();
    }

    /**
     * Counting starts now, not at the next hourly run: somebody who has just
     * told the app where their family begins and sees no change concludes it
     * did not work.
     *
     * @return int how many people the branch gained
     */
    private function recomputeDepths(FamilyBranch $branch): int
    {
        return $this->anchor->handle($branch->loadMissing('clan'));
    }

    /**
     * Where a branch may be created is not the same question as whether this
     * account may create branches at all.
     *
     * @param  array{tribe_id: int|null, clan_id: int|null}  $placement
     */
    private function assertMayManageIn(User $user, array $placement): void
    {
        $path = $this->scopePathFor((new FamilyBranch)->forceFill($placement));

        if ($path !== null && ! $this->permissions->can($user, 'families.manage', $path)) {
            throw new AuthorizationException('You may not add a family branch there.');
        }
    }

    /** @param  class-string<Model>  $model */
    private function idFor(string $model, ?string $ulid): ?int
    {
        return $ulid === null ? null : $model::where('ulid', $ulid)->value('id');
    }
}
