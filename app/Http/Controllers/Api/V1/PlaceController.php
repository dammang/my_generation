<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StorePlaceRequest;
use App\Http\Resources\V1\PlaceResource;
use App\Models\Place;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $places = Place::query()
            ->when($request->filled('q'), fn (Builder $q) => $q->where(
                fn (Builder $q) => $q
                    ->where('name', 'like', $request->string('q').'%')
                    ->orWhere('native_name', 'like', $request->string('q').'%')
            ))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')))
            ->when($request->filled('country'), fn (Builder $q) => $q->where(
                'country_code',
                $request->string('country')->upper()->toString()
            ))
            ->when(
                $request->filled('parent'),
                fn (Builder $q) => $q->where('parent_id', Place::where('ulid', $request->string('parent'))->value('id')),
                // Without a parent filter, default to the roots. Returning every
                // village in the gazetteer as a flat list helps nobody.
                fn (Builder $q) => $request->filled('q') ? $q : $q->whereNull('parent_id'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 50));

        return ApiResponse::success(PlaceResource::collection($places));
    }

    public function store(StorePlaceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $place = Place::create([
            ...collect($data)->except('parent_ulid')->all(),
            'parent_id' => isset($data['parent_ulid'])
                ? Place::where('ulid', $data['parent_ulid'])->value('id')
                : null,
        ]);

        // depth and the materialised path are maintained by PlaceObserver.
        return ApiResponse::created(PlaceResource::make($place->fresh()));
    }

    public function show(Place $place): JsonResponse
    {
        return ApiResponse::success(
            PlaceResource::make($place),
            meta: [
                'full_name' => $place->fullName(),
                'depth' => $place->depth,
            ],
        );
    }

    public function children(Place $place): JsonResponse
    {
        return ApiResponse::success(
            PlaceResource::collection($place->children()->orderBy('name')->get())
        );
    }
}
