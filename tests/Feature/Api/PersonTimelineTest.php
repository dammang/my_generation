<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\EventType;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The chronicle is the half of the product that is not a graph. A timeline
 * leaking a living person's life is the same failure as leaking the person.
 */
class PersonTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(EventTypeSeeder::class);

        $this->tribe = Tribe::factory()->create();
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
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Public,
            ...$overrides,
        ]);
    }

    /**
     * The year column is derived from the date, so the test sets the date —
     * writing event_year directly would be overwritten and prove nothing.
     */
    private function event(Person $person, string $slug, string $date): PersonEvent
    {
        $event = PersonEvent::factory()->make([
            'person_id' => $person->id,
            'event_type_id' => EventType::where('slug', $slug)->value('id'),
        ]);

        $event->setUncertainDate('event', $date)->save();

        return $event;
    }

    public function test_a_timeline_is_returned_oldest_first(): void
    {
        $person = $this->person(['is_living' => false]);
        $person->setUncertainDate('death', '1974')->save();

        $this->event($person, 'death', '1974');
        $this->event($person, 'birth', '1901');

        $response = $this->actingAs($this->contributor())
            ->getJson(route('api.v1.people.timeline', $person))
            ->assertOk()
            ->assertJsonPath('meta.withheld', false);

        $this->assertSame([1901, 1974], $response->json('data.*.year'));
    }

    public function test_a_timeline_is_withheld_when_the_person_is_masked(): void
    {
        // A living, tribe-scoped person: an outsider cannot reach them at all,
        // so the timeline must not be an alternative route to the same facts.
        $person = $this->person(['is_living' => true, 'privacy_level' => PrivacyLevel::Tribe]);
        $this->event($person, 'birth', '1990');

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.people.timeline', $person))
            ->assertNotFound();
    }

    public function test_an_uncertain_date_keeps_its_wording(): void
    {
        $person = $this->person(['is_living' => false]);
        $person->setUncertainDate('death', '1974')->save();

        $response = $this->actingAs($this->contributor())
            ->postJson(route('api.v1.events.store'), [
                'person_ulid' => $person->ulid,
                'event_type' => 'migration',
                'date' => 'abt. 1902',
                'title' => 'Left the Chin Hills',
            ])
            ->assertCreated();

        $this->assertSame(1902, $response->json('data.year'));
        $this->assertSame('about', $response->json('data.date_precision'));
        // "abt. 1902" is evidence. Rendering it as 1902 upgrades a guess.
        $this->assertStringContainsString('1902', (string) $response->json('data.date_display'));
    }

    public function test_recording_a_death_for_a_living_person_warns(): void
    {
        // The chronicle and the person's own death date are separate records,
        // so they can disagree — and a profile that says "Living" above a
        // timeline ending in a funeral makes the whole archive look careless.
        $person = $this->person(['is_living' => true]);
        $person->setUncertainDate('birth', '1980')->save();

        $response = $this->actingAs($this->contributor())
            ->postJson(route('api.v1.events.store'), [
                'person_ulid' => $person->ulid,
                'event_type' => 'death',
                'date' => '2020',
            ])
            ->assertCreated();

        $this->assertSame(
            'DEATH_EVENT_FOR_LIVING_PERSON',
            $response->json('warnings.0.code'),
        );

        // A warning, not a refusal: the event is kept.
        $this->assertDatabaseCount('person_events', 1);
    }

    public function test_recording_a_death_for_a_deceased_person_does_not_warn(): void
    {
        $person = $this->person(['is_living' => false]);
        $person->setUncertainDate('death', '1998')->save();

        $this->actingAs($this->contributor())
            ->postJson(route('api.v1.events.store'), [
                'person_ulid' => $person->ulid,
                'event_type' => 'death',
                'date' => '1998',
            ])
            ->assertCreated()
            ->assertJsonPath('warnings', []);
    }

    public function test_the_event_vocabulary_is_listed(): void
    {
        $slugs = $this->actingAs($this->contributor())
            ->getJson(route('api.v1.event-types.index'))
            ->assertOk()
            ->json('data.*.slug');

        $this->assertContains('birth', $slugs);
        $this->assertContains('migration', $slugs);
    }
}
