<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Genealogy\AddRelative;
use App\Enums\RelationshipSubtype;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PersonEventResource;
use App\Http\Resources\V1\PersonResource;
use App\Models\EventType;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\User;
use App\Services\Privacy\ViewerScope;
use App\Services\Statistics\ContributionCounter;
use App\Services\Sync\IdempotencyLedger;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Draining a phone's offline queue in one round trip.
 *
 * Deliberately *typed* rather than a generic request forwarder. Re-dispatching
 * arbitrary paths through the router would create a second way into every
 * endpoint, with its own chances to get authorization wrong; these operations
 * call the same actions and the same policies as the online routes.
 *
 * Operations are applied in the order given and independently: one that fails
 * does not stop the rest, because a queue where a single bad entry blocks
 * everything behind it is a queue that never drains. Each carries its own
 * operation id, so replaying the whole batch is safe.
 */
class SyncController extends Controller
{
    public function __construct(
        private readonly IdempotencyLedger $ledger,
        private readonly ViewerScope $viewer,
    ) {}

    public function batch(Request $request, AddRelative $addRelative, ContributionCounter $contributions): JsonResponse
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.client_operation_id' => ['required', 'uuid'],
            'operations.*.kind' => ['required', 'string', 'in:add_relative,add_event,edit_person'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $user = $request->user();
        $results = [];

        foreach ($data['operations'] as $operation) {
            $results[] = $this->apply($user, $operation, $addRelative, $contributions);
        }

        return ApiResponse::success($results, meta: [
            'applied' => count(array_filter($results, fn (array $r) => $r['status'] === 'applied')),
            'failed' => count(array_filter($results, fn (array $r) => $r['status'] === 'failed')),
            'replayed' => count(array_filter($results, fn (array $r) => $r['status'] === 'replayed')),
        ]);
    }

    /**
     * @param  array{client_operation_id: string, kind: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function apply(
        User $user,
        array $operation,
        AddRelative $addRelative,
        ContributionCounter $contributions,
    ): array {
        $key = $operation['client_operation_id'];
        $kind = $operation['kind'];
        $payload = $operation['payload'];

        $seen = $this->ledger->claim($user, $key, 'sync '.$kind, $this->ledger->hash($kind, $payload));

        if ($seen !== null) {
            // Already done, or being done. Either way this must not run again;
            // the stored answer is the honest one.
            return [
                'client_operation_id' => $key,
                'status' => 'replayed',
                'code' => $seen->response_code,
                'data' => $seen->response_body['data'] ?? null,
            ];
        }

        try {
            $result = match ($kind) {
                'add_relative' => $this->addRelative($user, $payload, $addRelative),
                'add_event' => $this->addEvent($user, $payload, $contributions),
                'edit_person' => $this->editPerson($user, $payload),
            };

            $this->ledger->settle($user, $key, 200, ['data' => $result]);

            return [
                'client_operation_id' => $key,
                'status' => 'applied',
                'code' => 200,
                'data' => $result,
            ];
        } catch (ValidationException $e) {
            return $this->failed($user, $key, 422, $e->getMessage(), 'VALIDATION_FAILED', $e->errors());
        } catch (AuthorizationException $e) {
            return $this->failed($user, $key, 403, $e->getMessage(), 'FORBIDDEN');
        } catch (ApiException $e) {
            return $this->failed($user, $key, $e->status(), $e->getMessage(), $e->errorCode(), $e->errors());
        }
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function failed(
        User $user,
        string $key,
        int $status,
        string $message,
        string $code,
        array $errors = [],
    ): array {
        // Recorded, not released: a rejected operation replayed unchanged would
        // be rejected again, and the client needs to stop retrying it and show
        // the person what went wrong.
        $this->ledger->settle($user, $key, $status, ['message' => $message, 'code' => $code]);

        return [
            'client_operation_id' => $key,
            'status' => 'failed',
            'code' => $status,
            'message' => $message,
            'error_code' => $code,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function addRelative(User $user, array $payload, AddRelative $action): array
    {
        $anchor = $this->personFor($payload['anchor_ulid'] ?? null);

        if ($user->cannot('update', $anchor)) {
            throw new AuthorizationException('You may not add relatives to this person.');
        }

        $outcome = $action->handle(
            author: $user,
            anchor: $anchor,
            relation: (string) ($payload['relation'] ?? 'child'),
            attributes: (array) ($payload['person'] ?? []),
            subtype: RelationshipSubtype::tryFrom((string) ($payload['subtype'] ?? '')) ?? RelationshipSubtype::Biological,
        );

        return [
            'person' => PersonResource::make($outcome->record)->resolve(),
            'created' => $outcome->created,
            'warnings' => $outcome->warningPayload(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function addEvent(User $user, array $payload, ContributionCounter $contributions): array
    {
        $person = $this->personFor($payload['person_ulid'] ?? null);

        if ($user->cannot('update', $person)) {
            throw new AuthorizationException('You may not add events to this person.');
        }

        $event = new PersonEvent([
            'person_id' => $person->getKey(),
            'event_type_id' => EventType::where('slug', $payload['event_type'] ?? 'other')->value('id'),
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);

        if (isset($payload['date'])) {
            $event->setUncertainDate('event', (string) $payload['date']);
        }

        $event->save();

        $contributions->increment($user, 'events_added');

        return ['event' => PersonEventResource::make($event->load('eventType'))->resolve()];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function editPerson(User $user, array $payload): array
    {
        $person = $this->personFor($payload['ulid'] ?? null);

        if ($user->cannot('update', $person)) {
            throw new AuthorizationException('You may not change this person.');
        }

        $changes = (array) ($payload['changes'] ?? []);

        $person->withRevisionContext(reason: $payload['reason'] ?? null);
        $person->fill(collect($changes)->except(['birth', 'death'])->all());

        foreach (['birth', 'death'] as $prefix) {
            if (array_key_exists($prefix, $changes)) {
                $person->setUncertainDate($prefix, $changes[$prefix]);
            }
        }

        $person->save();

        return ['person' => PersonResource::make($person->fresh())->resolve()];
    }

    private function personFor(mixed $ulid): Person
    {
        $person = is_string($ulid)
            ? Person::where('ulid', $ulid)->visibleTo($this->viewer)->first()
            : null;

        if ($person === null) {
            throw ValidationException::withMessages([
                'person_ulid' => ['That person is not available.'],
            ]);
        }

        return $person;
    }
}
