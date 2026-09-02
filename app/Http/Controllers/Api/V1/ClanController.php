<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreClanRequest;
use App\Http\Requests\V1\UpdateClanRequest;
use App\Http\Resources\V1\ClanResource;
use App\Http\Resources\V1\FamilyBranchResource;
use App\Models\Clan;
use App\Models\Tribe;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clans = Clan::query()
            ->when($request->filled('tribe'), fn (Builder $q) => $q->where(
                'tribe_id',
                Tribe::where('ulid', $request->string('tribe'))->value('id')
            ))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', $request->string('q').'%'))
            ->with('tribe:id,ulid,name')
            ->withCount('childClans')
            ->orderBy('depth')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return ApiResponse::success(ClanResource::collection($clans));
    }

    public function store(StoreClanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $clan = Clan::create([
            ...collect($data)->except(['tribe_ulid', 'parent_clan_ulid'])->all(),
            'tribe_id' => Tribe::where('ulid', $data['tribe_ulid'])->value('id'),
            'parent_clan_id' => isset($data['parent_clan_ulid'])
                ? Clan::where('ulid', $data['parent_clan_ulid'])->value('id')
                : null,
        ]);

        // depth, path and the scope row are all maintained by observers.
        return ApiResponse::created(ClanResource::make($clan->load('tribe:id,ulid,name')));
    }

    public function show(Clan $clan): JsonResponse
    {
        $clan->loadCount('childClans');
        $clan->load(['tribe:id,ulid,name', 'parentClan:id,ulid,name', 'childClans']);

        return ApiResponse::success(ClanResource::make($clan));
    }

    public function update(UpdateClanRequest $request, Clan $clan): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('parent_clan_ulid', $data)) {
            $data['parent_clan_id'] = $data['parent_clan_ulid'] === null
                ? null
                : Clan::where('ulid', $data['parent_clan_ulid'])->value('id');
            unset($data['parent_clan_ulid']);
        }

        // Re-parenting rewrites this clan's path and every path beneath it, so
        // permission checks below the move keep answering with the real
        // hierarchy. ScopedEntityObserver handles that.
        $clan->update($data);

        return ApiResponse::success(ClanResource::make($clan->fresh(['tribe:id,ulid,name', 'parentClan:id,ulid,name'])));
    }

    public function destroy(Clan $clan): JsonResponse
    {
        $this->authorize('delete', $clan);

        if ($clan->childClans()->exists() || $clan->people()->exists() || $clan->familyBranches()->exists()) {
            return ApiResponse::error(
                'This clan still has sub-clans, branches or people. Move or remove them first.',
                409,
                [],
                'SCOPE_NOT_EMPTY',
            );
        }

        $clan->delete();

        return ApiResponse::noContent();
    }

    public function branches(Clan $clan): JsonResponse
    {
        $branches = $clan->familyBranches()
            ->with(['tribe:id,ulid,name', 'ancestor', 'originPlace'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(FamilyBranchResource::collection($branches));
    }
}
