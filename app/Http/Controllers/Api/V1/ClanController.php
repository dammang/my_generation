<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\AnchorClanGenerations;
use App\Actions\Genealogy\AnchorFamilyBranch;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreClanRequest;
use App\Http\Requests\V1\UpdateClanRequest;
use App\Http\Resources\V1\ClanResource;
use App\Http\Resources\V1\FamilyBranchResource;
use App\Models\Clan;
use App\Models\Person;
use App\Models\Tribe;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly AnchorClanGenerations $clanGenerations,
        private readonly AnchorFamilyBranch $anchor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Asking to join needs a list to choose from, and somebody who belongs
        // to nothing can see nothing — which left the joining screen empty for
        // exactly the people it exists for. A clan's name and size is the
        // least anybody needs to ask, and carries nothing about a person.
        $joining = $request->boolean('joinable');

        $clans = Clan::query()
            ->when(! $joining, fn (Builder $q) => $q->visibleTo($this->viewer))
            ->when($joining, fn (Builder $q) => $q->where('status', RecordStatus::Active))
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
        $clan->load(['tribe:id,ulid,name', 'parentClan:id,ulid,name', 'childClans', 'ancestor', 'countingOrigin']);

        return ApiResponse::success(ClanResource::make($clan));
    }

    public function update(UpdateClanRequest $request, Clan $clan): JsonResponse
    {
        $data = $request->validated();

        foreach ([
            'ancestor_person_ulid' => 'ancestor_person_id',
            'counting_origin_person_ulid' => 'counting_origin_person_id',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $data[$column] = $data[$input] === null
                    ? null
                    : Person::where('ulid', $data[$input])->value('id');
                unset($data[$input]);
            }
        }

        if (array_key_exists('parent_clan_ulid', $data)) {
            $data['parent_clan_id'] = $data['parent_clan_ulid'] === null
                ? null
                : Clan::where('ulid', $data['parent_clan_ulid'])->value('id');
            unset($data['parent_clan_ulid']);
        }

        $scaleChanged = collect(['ancestor_person_id', 'counting_origin_person_id'])
            ->contains(fn (string $column) => array_key_exists($column, $data)
                && $data[$column] !== $clan->{$column});

        // Re-parenting rewrites this clan's path and every path beneath it, so
        // permission checks below the move keep answering with the real
        // hierarchy. ScopedEntityObserver handles that.
        $clan->update($data);

        if ($scaleChanged) {
            $this->recountFrom($clan);
        }

        return ApiResponse::success(ClanResource::make(
            $clan->fresh(['tribe:id,ulid,name', 'parentClan:id,ulid,name', 'ancestor', 'countingOrigin']),
        ));
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

    /**
     * The clan's own scale moved, so everything measured against it is stale.
     *
     * Every branch inside the clan reports its founder's number on that scale
     * — "the 11th generation from Pu Zo" — and a clan that names a different
     * ancestor makes all of those wrong at once, silently.
     */
    private function recountFrom(Clan $clan): void
    {
        $this->clanGenerations->handle($clan);

        foreach ($clan->familyBranches()->whereNotNull('ancestor_person_id')->get() as $branch) {
            $this->anchor->handle($branch->setRelation('clan', $clan));
        }
    }

    public function branches(Clan $clan): JsonResponse
    {
        $branches = $clan->familyBranches()
            ->visibleTo($this->viewer)
            ->with(['tribe:id,ulid,name', 'ancestor', 'originPlace'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(FamilyBranchResource::collection($branches));
    }
}
