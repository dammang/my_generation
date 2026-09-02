<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RevisionResource;
use App\Models\Person;
use App\Models\Revision;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What this record used to say, and who changed it.
 *
 * History is the answer to "that's not what my grandmother told me": it shows
 * the correction, who made it and why, rather than presenting the current
 * value as though it had always been so.
 */
class RevisionController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PersonVisibilityResolver $visibility,
    ) {}

    public function forPerson(Request $request, Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        // History is at least as revealing as the record, and field-level: it
        // carries the exact values. A viewer shown only approximate dates must
        // not be able to read the precise ones out of the changes to them.
        if (! $this->visibility->mask($this->viewer, $person)->exactDates) {
            return ApiResponse::success([], meta: ['withheld' => true]);
        }

        $revisions = Revision::query()
            ->where('revisionable_type', $person->getMorphClass())
            ->where('revisionable_id', $person->getKey())
            ->with(['changedBy:id,ulid,name', 'source:id,ulid,title'])
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success(
            RevisionResource::collection($revisions),
            meta: ['withheld' => false],
        );
    }
}
