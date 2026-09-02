<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StorePersonEventRequest;
use App\Http\Resources\V1\PersonEventResource;
use App\Models\EventType;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Place;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;
use App\Services\Statistics\ContributionCounter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chronicle: births, migrations, service, death.
 *
 * This is the half of the product that preserves history rather than
 * relationships — a person is more than their position in a graph.
 */
class PersonEventController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PersonVisibilityResolver $visibility,
    ) {}

    /**
     * One person's timeline, oldest first.
     *
     * Events are withheld entirely from a viewer who may not see the person's
     * details — a life story is exactly the kind of content the living-person
     * mask exists to protect.
     */
    public function timeline(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        if (! $this->visibility->mask($this->viewer, $person)->events) {
            return ApiResponse::success([], meta: ['withheld' => true]);
        }

        $events = $person->events()
            ->with(['eventType', 'place', 'fromPlace', 'toPlace'])
            ->orderBy('event_year')
            ->orderBy('event_date')
            ->get();

        return ApiResponse::success(
            PersonEventResource::collection($events),
            meta: ['withheld' => false, 'count' => $events->count()],
        );
    }

    public function store(StorePersonEventRequest $request, ContributionCounter $contributions): JsonResponse
    {
        $data = $request->validated();

        $person = Person::where('ulid', $data['person_ulid'])
            ->visibleTo($this->viewer)
            ->firstOrFail();

        $this->authorize('update', $person);

        $event = new PersonEvent([
            'person_id' => $person->getKey(),
            'event_type_id' => EventType::where('slug', $data['event_type'])->value('id'),
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'place_id' => $this->placeId($data['place_ulid'] ?? null),
            'from_place_id' => $this->placeId($data['from_place_ulid'] ?? null),
            'to_place_id' => $this->placeId($data['to_place_ulid'] ?? null),
            'privacy_level' => $data['privacy_level'] ?? null,
        ]);

        if (isset($data['date'])) {
            $event->setUncertainDate('event', $data['date']);
        }

        $event->save();

        $contributions->increment($request->user(), 'events_added');

        return ApiResponse::created(
            PersonEventResource::make($event->load(['eventType', 'place', 'fromPlace', 'toPlace'])),
            warnings: $this->contradictionWarnings($person, $data['event_type']),
        );
    }

    public function update(Request $request, PersonEvent $personEvent): JsonResponse
    {
        $this->authorize('update', $personEvent);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'date' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $reason = $data['reason'] ?? null;
        $date = $data['date'] ?? null;
        unset($data['reason'], $data['date']);

        $personEvent->withRevisionContext(reason: $reason)->fill($data);

        if (array_key_exists('date', $request->all())) {
            $personEvent->setUncertainDate('event', $date);
        }

        $personEvent->save();

        return ApiResponse::success(
            PersonEventResource::make($personEvent->fresh(['eventType', 'place']))
        );
    }

    public function destroy(PersonEvent $personEvent): JsonResponse
    {
        $this->authorize('delete', $personEvent);

        $personEvent->delete();

        return ApiResponse::noContent();
    }

    /** The event vocabulary, including anything a tribe has added. */
    public function types(Request $request): JsonResponse
    {
        $types = EventType::query()
            ->orderBy('sort_order')
            ->get(['slug', 'label', 'category', 'icon']);

        return ApiResponse::success(
            $types->map(fn (EventType $type) => [
                'slug' => $type->slug,
                'label' => $type->label,
                'category' => $type->category->value,
                'icon' => $type->icon,
            ])->all()
        );
    }

    /**
     * A death recorded against somebody still marked living.
     *
     * The person's death date and their chronicle are separate records, so
     * nothing stops the two disagreeing — and a profile reading "Living" above
     * a timeline that ends in a funeral is the kind of contradiction that makes
     * a family distrust the whole archive. A warning, not a refusal: the event
     * is usually right and the flag is what needs updating.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contradictionWarnings(Person $person, string $eventType): array
    {
        $endsALife = in_array($eventType, ['death', 'burial', 'memorial'], true);

        if (! $endsALife || ! $person->is_living) {
            return [];
        }

        return [[
            'code' => 'DEATH_EVENT_FOR_LIVING_PERSON',
            'message' => $person->display_name.' is still recorded as living. '
                .'Add a date of death to the record so the two agree.',
            'field' => 'is_living',
        ]];
    }

    private function placeId(?string $ulid): ?int
    {
        return $ulid === null ? null : Place::where('ulid', $ulid)->value('id');
    }
}
