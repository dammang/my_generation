<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreTribeRequest;
use App\Http\Requests\V1\UpdateTribeRequest;
use App\Http\Resources\V1\ClanResource;
use App\Http\Resources\V1\TribeResource;
use App\Models\Clan;
use App\Models\Tribe;
use App\Services\Privacy\ViewerScope;
use App\Services\Statistics\ScopeStatistics;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribeController extends Controller
{
    public function __construct(private readonly ViewerScope $viewer) {}

    public function index(Request $request): JsonResponse
    {
        $tribes = Tribe::query()
            ->when($request->filled('q'), fn (Builder $q) => $q->where(
                fn (Builder $q) => $q
                    ->where('name', 'like', $request->string('q').'%')
                    ->orWhere('native_name', 'like', $request->string('q').'%')
            ))
            ->when($request->filled('country'), fn (Builder $q) => $q->where(
                'country_code',
                $request->string('country')->upper()->toString()
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return ApiResponse::success(TribeResource::collection($tribes));
    }

    public function store(StoreTribeRequest $request): JsonResponse
    {
        // The ScopedEntityObserver creates the scope row, so a tribe is
        // administrable the moment it exists.
        $tribe = Tribe::create($request->validated());

        return ApiResponse::created(TribeResource::make($tribe));
    }

    public function show(Tribe $tribe): JsonResponse
    {
        $tribe->load(['rootClans', 'generations']);

        return ApiResponse::success(TribeResource::make($tribe));
    }

    public function update(UpdateTribeRequest $request, Tribe $tribe): JsonResponse
    {
        $tribe->update($request->validated());

        return ApiResponse::success(TribeResource::make($tribe));
    }

    public function destroy(Tribe $tribe): JsonResponse
    {
        $this->authorize('delete', $tribe);

        // Structural parents are RESTRICT in the schema. Answering plainly here
        // is better than letting a foreign key error surface as a 500.
        if ($tribe->clans()->exists() || $tribe->people()->exists()) {
            return ApiResponse::error(
                'This tribe still has clans or people. Move or remove them first.',
                409,
                [],
                'SCOPE_NOT_EMPTY',
            );
        }

        $tribe->delete();

        return ApiResponse::noContent();
    }

    /**
     * The clan hierarchy, one level at a time. `parent=root` returns top-level
     * clans; passing a clan ULID returns its children. Returning the whole
     * hierarchy would be unbounded for a large tribe.
     */
    public function clans(Request $request, Tribe $tribe): JsonResponse
    {
        $parent = $request->string('parent', 'root')->toString();

        $clans = Clan::query()
            ->visibleTo($this->viewer)
            ->where('tribe_id', $tribe->getKey())
            ->when(
                $parent === 'root',
                fn (Builder $q) => $q->whereNull('parent_clan_id'),
                fn (Builder $q) => $q->where(
                    'parent_clan_id',
                    Clan::where('ulid', $parent)->value('id')
                ),
            )
            ->withCount('childClans')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ClanResource::collection($clans));
    }

    public function statistics(Tribe $tribe, ScopeStatistics $statistics): JsonResponse
    {
        return ApiResponse::success($statistics->forTribe($tribe));
    }
}
