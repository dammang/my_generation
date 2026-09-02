<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreFamilyBranchRequest;
use App\Http\Requests\V1\UpdateFamilyBranchRequest;
use App\Http\Resources\V1\FamilyBranchResource;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Place;
use App\Models\Tribe;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyBranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branches = FamilyBranch::query()
            ->when($request->filled('tribe'), fn (Builder $q) => $q->where(
                'tribe_id',
                Tribe::where('ulid', $request->string('tribe'))->value('id')
            ))
            ->when($request->filled('clan'), fn (Builder $q) => $q->where(
                'clan_id',
                Clan::where('ulid', $request->string('clan'))->value('id')
            ))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', $request->string('q').'%'))
            ->with(['tribe:id,ulid,name', 'clan:id,ulid,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return ApiResponse::success(FamilyBranchResource::collection($branches));
    }

    public function store(StoreFamilyBranchRequest $request): JsonResponse
    {
        $data = $request->validated();

        $branch = FamilyBranch::create([
            ...collect($data)->except([
                'tribe_ulid', 'clan_ulid', 'ancestor_person_ulid', 'origin_place_ulid',
            ])->all(),
            'tribe_id' => Tribe::where('ulid', $data['tribe_ulid'])->value('id'),
            'clan_id' => $this->idFor(Clan::class, $data['clan_ulid'] ?? null),
            'ancestor_person_id' => $this->idFor(Person::class, $data['ancestor_person_ulid'] ?? null),
            'origin_place_id' => $this->idFor(Place::class, $data['origin_place_ulid'] ?? null),
        ]);

        return ApiResponse::created(
            FamilyBranchResource::make($branch->load(['tribe:id,ulid,name', 'clan:id,ulid,name']))
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

        return ApiResponse::success(
            FamilyBranchResource::make($familyBranch->fresh(['tribe:id,ulid,name', 'clan:id,ulid,name', 'ancestor']))
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

    /** @param  class-string<Model>  $model */
    private function idFor(string $model, ?string $ulid): ?int
    {
        return $ulid === null ? null : $model::where('ulid', $ulid)->value('id');
    }
}
