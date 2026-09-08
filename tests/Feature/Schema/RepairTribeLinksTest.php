<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Models\Clan;
use App\Models\Person;
use App\Models\Tribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepairTribeLinksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_gives_people_the_tribe_their_clan_belongs_to(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $person = Person::factory()->create(['clan_id' => $clan->id]);
        Person::withoutEvents(fn () => $person->forceFill(['tribe_id' => null])->save());

        $this->artisan('archive:repair-tribe-links', ['--force' => true])
            ->assertSuccessful();

        // Permissions granted at the tribe are evaluated against this column,
        // so a tribe admin quietly had no authority over their own clan's
        // people while it was empty.
        $this->assertSame($tribe->id, $person->refresh()->tribe_id);
    }

    #[Test]
    public function it_keeps_the_counters_the_observer_maintains(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $person = Person::factory()->create(['clan_id' => $clan->id]);
        Person::withoutEvents(fn () => $person->forceFill(['tribe_id' => null])->save());

        $before = $tribe->refresh()->people_count;

        $this->artisan('archive:repair-tribe-links', ['--force' => true]);

        // Saved through the model rather than updated in bulk: the tribe's
        // count of itself is maintained by the observer, and a bulk update
        // would leave it reading zero for three hundred people.
        $this->assertSame($before + 1, $tribe->refresh()->people_count);
    }

    #[Test]
    public function it_changes_nothing_without_force(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $person = Person::factory()->create(['clan_id' => $clan->id]);
        Person::withoutEvents(fn () => $person->forceFill(['tribe_id' => null])->save());

        $this->artisan('archive:repair-tribe-links')->assertSuccessful();

        $this->assertNull($person->refresh()->tribe_id);
    }
}
