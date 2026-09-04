<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreStoryRequest;
use App\Http\Resources\V1\StoryResource;
use App\Models\Person;
use App\Models\Story;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Family narratives — the half of an archive that is not a graph.
 *
 * A tree records that somebody existed and who they descended from. A story
 * is the only place the archive holds why anybody would want to know.
 */
class StoryController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PersonVisibilityResolver $visibility,
    ) {}

    /**
     * Stories the viewer may read, newest first.
     *
     * Filtered by person when asked for, which is how the person screen shows
     * "stories about this person" without a second endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Story::class);

        $stories = Story::query()
            ->visibleTo($this->viewer)
            ->when($request->filled('person_ulid'), function ($query) use ($request) {
                $person = Person::where('ulid', $request->string('person_ulid'))
                    ->visibleTo($this->viewer)
                    ->first();

                // A person the viewer cannot see yields no stories rather
                // than every story: a missing filter must never widen a
                // result set.
                return $query->where('person_id', $person?->getKey() ?? 0);
            })
            ->when($request->filled('tribe_ulid'), fn ($query) => $query
                ->whereHas('tribe', fn ($q) => $q->where('ulid', $request->string('tribe_ulid'))))
            ->with(['author:id,ulid,name', 'subject:id,ulid,display_name'])
            ->latest('created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return ApiResponse::success(StoryResource::collection($stories));
    }

    /** One story, with its body. */
    public function show(Story $story): JsonResponse
    {
        $this->authorize('view', $story);

        return ApiResponse::success(
            StoryResource::full($story->load(['author:id,ulid,name', 'subject:id,ulid,display_name']))
        );
    }

    public function store(StoreStoryRequest $request): JsonResponse
    {
        $this->authorize('create', Story::class);

        $data = $request->validated();
        $person = null;

        if (! empty($data['person_ulid'])) {
            $person = Person::where('ulid', $data['person_ulid'])
                ->visibleTo($this->viewer)
                ->firstOrFail();

            // The living-person mask governs a story the same way it governs a
            // timeline: if the viewer may not read this person's life, they
            // may not write one onto the record either.
            if (! $this->visibility->mask($this->viewer, $person)->biography) {
                return ApiResponse::error(
                    'You cannot add a story to this person.',
                    403,
                    code: 'PERSON_NOT_WRITABLE',
                );
            }
        }

        $story = Story::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'summary' => $data['summary'] ?? null,
            'person_id' => $person?->getKey(),
            'tribe_id' => $person?->tribe_id,
            'clan_id' => $person?->clan_id,
            'family_branch_id' => $person?->family_branch_id,
            'author_id' => $request->user()->getKey(),
            'created_by' => $request->user()->getKey(),
            'story_type' => $data['story_type'] ?? StoryType::Narrative,
            'visibility' => $data['visibility'] ?? PrivacyLevel::Family,
            'era_start_year' => $data['era_start_year'] ?? null,
            'era_end_year' => $data['era_end_year'] ?? null,
        ]);

        return ApiResponse::created(
            StoryResource::full($story->load(['author:id,ulid,name', 'subject:id,ulid,display_name']))
        );
    }
}
