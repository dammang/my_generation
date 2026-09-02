<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreGenerationRequest;
use App\Http\Resources\V1\GenerationResource;
use App\Models\Clan;
use App\Models\Generation;
use App\Models\Tribe;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generation labels. Nothing in the traversal engine depends on these — a
 * missing or wrong number degrades a caption, never the tree.
 */
class GenerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $generations = Generation::query()
            ->when($request->filled('tribe'), fn (Builder $q) => $q->where(
                'tribe_id',
                Tribe::where('ulid', $request->string('tribe'))->value('id')
            ))
            ->when($request->filled('clan'), fn (Builder $q) => $q->where(
                'clan_id',
                Clan::where('ulid', $request->string('clan'))->value('id')
            ))
            ->orderBy('generation_number')
            ->get();

        return ApiResponse::success(GenerationResource::collection($generations));
    }

    public function store(StoreGenerationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $generation = Generation::create([
            ...collect($data)->except(['tribe_ulid', 'clan_ulid'])->all(),
            'tribe_id' => Tribe::where('ulid', $data['tribe_ulid'])->value('id'),
            'clan_id' => isset($data['clan_ulid'])
                ? Clan::where('ulid', $data['clan_ulid'])->value('id')
                : null,
        ]);

        return ApiResponse::created(GenerationResource::make($generation));
    }

    public function update(Request $request, Generation $generation): JsonResponse
    {
        $this->authorize('update', $generation->tribe);

        $validated = $request->validate([
            'generation_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'local_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estimated_start_year' => ['sometimes', 'nullable', 'integer', 'between:-3000,2200'],
            'estimated_end_year' => ['sometimes', 'nullable', 'integer', 'between:-3000,2200'],
        ]);

        $generation->update($validated);

        return ApiResponse::success(GenerationResource::make($generation));
    }

    public function destroy(Generation $generation): JsonResponse
    {
        $this->authorize('update', $generation->tribe);

        // people.generation_id is SET NULL, so removing a label leaves the
        // people intact and simply unlabelled.
        $generation->delete();

        return ApiResponse::noContent();
    }
}
