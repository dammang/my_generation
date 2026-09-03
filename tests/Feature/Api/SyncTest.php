<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\SyncOperation;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Sync\IdempotencyLedger;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Offline writes. The failure this guards against is quiet and permanent: a
 * phone that never heard the acknowledgement retries, and the family ends up
 * with two grandfathers who then have to be found and merged by hand.
 */
class SyncTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(EventTypeSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $this->branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
        ]);
    }

    /**
     * People named Thawng **in this test's own tribe**.
     *
     * Eight other test files also create a Thawng. Counting across the whole
     * database makes every assertion here quietly depend on all of them having
     * rolled back cleanly, which turns an unrelated change into a failure in
     * this file.
     */
    private function thawngCount(): int
    {
        return $this->countNamed('Thawng');
    }

    private function countNamed(string $firstName): int
    {
        return Person::where('first_name', $firstName)
            ->where('tribe_id', $this->tribe->id)
            ->count();
    }

    private function contributor(): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe->id)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        $user->assignRole('contributor');

        return $user;
    }

    private function person(array $overrides = []): Person
    {
        $person = Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Public,
            'is_living' => false,
            ...$overrides,
        ]);

        $person->setUncertainDate('death', '1998')->save();

        return $person;
    }

    public function test_a_retried_write_does_not_create_a_second_person(): void
    {
        $anchor = $this->person();
        $user = $this->contributor();
        $key = (string) Str::uuid();

        $body = [
            'relation' => 'father',
            'person' => ['first_name' => 'Thawng', 'last_name' => 'Dam'],
            'client_operation_id' => $key,
        ];

        $first = $this->actingAs($user)
            ->postJson(route('api.v1.people.relatives', $anchor), $body)
            ->assertSuccessful();

        // The phone never heard this answer and asks again.
        $second = $this->actingAs($user)
            ->postJson(route('api.v1.people.relatives', $anchor), $body)
            ->assertSuccessful();

        $this->assertSame(
            $first->json('data.person.ulid'),
            $second->json('data.person.ulid'),
        );
        $this->assertSame('true', $second->headers->get('Idempotent-Replay'));
        $this->assertSame(1, $this->thawngCount());
    }

    public function test_the_same_id_with_a_different_change_is_refused(): void
    {
        // Not a retry — a client bug. Replaying the first answer would report
        // that a change which never happened had succeeded.
        $anchor = $this->person();
        $user = $this->contributor();
        $key = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'father',
                'person' => ['first_name' => 'Thawng'],
                'client_operation_id' => $key,
            ])
            ->assertSuccessful();

        $this->actingAs($user)
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'mother',
                'person' => ['first_name' => 'Ngun'],
                'client_operation_id' => $key,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertSame(0, $this->countNamed('Ngun'));
    }

    public function test_a_client_may_supply_the_new_person_identifier(): void
    {
        // Offline, a new person must be referable before the server has ever
        // seen them, so an event recorded about a grandfather added on a plane
        // can name him.
        $anchor = $this->person();
        $ulid = (string) Str::ulid();

        $this->actingAs($this->contributor())
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'father',
                'person' => ['ulid' => $ulid, 'first_name' => 'Thawng'],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.person.ulid', $ulid);
    }

    public function test_a_supplied_identifier_that_already_exists_is_refused(): void
    {
        $anchor = $this->person();

        $this->actingAs($this->contributor())
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'father',
                'person' => ['ulid' => $anchor->ulid, 'first_name' => 'Thawng'],
            ])
            ->assertStatus(422);
    }

    public function test_a_batch_applies_operations_in_order(): void
    {
        $anchor = $this->person();
        $fatherUlid = (string) Str::ulid();

        $response = $this->actingAs($this->contributor())
            ->postJson(route('api.v1.sync.batch'), [
                'operations' => [
                    [
                        'client_operation_id' => (string) Str::uuid(),
                        'kind' => 'add_relative',
                        'payload' => [
                            'anchor_ulid' => $anchor->ulid,
                            'relation' => 'father',
                            'person' => ['ulid' => $fatherUlid, 'first_name' => 'Thawng'],
                        ],
                    ],
                    [
                        // Refers to the person the previous operation created —
                        // possible only because the client minted the id.
                        'client_operation_id' => (string) Str::uuid(),
                        'kind' => 'add_event',
                        'payload' => [
                            'person_ulid' => $fatherUlid,
                            'event_type' => 'migration',
                            'date' => 'abt. 1902',
                            'title' => 'Left the Chin Hills',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(2, $response->json('meta.applied'));
        $this->assertSame(0, $response->json('meta.failed'));
        $this->assertSame(1902, $response->json('data.1.data.event.year'));
    }

    public function test_one_bad_operation_does_not_block_the_rest(): void
    {
        // A queue where a single bad entry stops everything behind it is a
        // queue that never drains.
        $anchor = $this->person();

        $response = $this->actingAs($this->contributor())
            ->postJson(route('api.v1.sync.batch'), [
                'operations' => [
                    [
                        'client_operation_id' => (string) Str::uuid(),
                        'kind' => 'add_event',
                        'payload' => ['person_ulid' => 'NOT-A-REAL-PERSON', 'event_type' => 'birth'],
                    ],
                    [
                        'client_operation_id' => (string) Str::uuid(),
                        'kind' => 'add_relative',
                        'payload' => [
                            'anchor_ulid' => $anchor->ulid,
                            'relation' => 'father',
                            'person' => ['first_name' => 'Thawng'],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame('failed', $response->json('data.0.status'));
        $this->assertSame('applied', $response->json('data.1.status'));
        $this->assertSame(1, $response->json('meta.applied'));
        $this->assertSame(1, $response->json('meta.failed'));
    }

    public function test_replaying_a_batch_changes_nothing(): void
    {
        $anchor = $this->person();
        $operations = [
            [
                'client_operation_id' => (string) Str::uuid(),
                'kind' => 'add_relative',
                'payload' => [
                    'anchor_ulid' => $anchor->ulid,
                    'relation' => 'father',
                    'person' => ['first_name' => 'Thawng'],
                ],
            ],
        ];

        $user = $this->contributor();

        $this->actingAs($user)
            ->postJson(route('api.v1.sync.batch'), ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('meta.applied', 1);

        $this->actingAs($user)
            ->postJson(route('api.v1.sync.batch'), ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('meta.replayed', 1);

        $this->assertSame(1, $this->thawngCount());
    }

    public function test_an_abandoned_claim_does_not_block_the_operation_forever(): void
    {
        // A process killed between claiming the key and recording the outcome
        // would otherwise leave that id pending for good, and the phone would
        // be told "still being applied" on every retry until the end of time.
        $anchor = $this->person();
        $user = $this->contributor();
        $key = (string) Str::uuid();

        SyncOperation::create([
            'user_id' => $user->id,
            'client_operation_id' => $key,
            'endpoint' => 'POST api/v1/people/x/relatives',
            'request_hash' => str_repeat('a', 64),
            'status' => 'pending',
            'response_code' => null,
        ])->forceFill(['created_at' => now()->subHour()])->save();

        $this->actingAs($user)
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'father',
                'person' => ['first_name' => 'Thawng'],
                'client_operation_id' => $key,
            ])
            ->assertSuccessful();

        $this->assertSame(1, $this->thawngCount());
    }

    public function test_a_claim_still_in_flight_is_not_run_twice(): void
    {
        $anchor = $this->person();
        $user = $this->contributor();
        $key = (string) Str::uuid();

        $body = [
            'relation' => 'father',
            'person' => ['first_name' => 'Thawng'],
        ];

        // Claimed the way the middleware claims it, so the hashes match and the
        // in-flight check is what answers rather than the reuse check.
        $ledger = app(IdempotencyLedger::class);
        $endpoint = 'POST api/v1/people/'.$anchor->ulid.'/relatives';

        $ledger->claim($user, $key, $endpoint, $ledger->hash($endpoint, $body));

        $this->actingAs($user)
            ->postJson(
                route('api.v1.people.relatives', $anchor),
                [...$body, 'client_operation_id' => $key],
            )
            ->assertStatus(409)
            ->assertJsonPath('code', 'OPERATION_IN_FLIGHT');

        $this->assertSame(0, $this->thawngCount());
    }

    public function test_a_rejected_operation_is_remembered_not_retried(): void
    {
        // Replaying a rejection unchanged would be rejected again. The client
        // needs to stop and show the person what went wrong.
        $key = (string) Str::uuid();
        $user = $this->contributor();

        $operations = [[
            'client_operation_id' => $key,
            'kind' => 'add_event',
            'payload' => ['person_ulid' => 'NOT-A-REAL-PERSON', 'event_type' => 'birth'],
        ]];

        $this->actingAs($user)
            ->postJson(route('api.v1.sync.batch'), ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'failed');

        $this->assertSame(
            'rejected',
            SyncOperation::where('client_operation_id', $key)->value('status')->value,
        );

        $this->actingAs($user)
            ->postJson(route('api.v1.sync.batch'), ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'replayed');
    }
}
