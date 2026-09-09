<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PrivacyLevel;
use App\Models\Person;
use App\Models\Tribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What level a new record starts at.
 *
 * The tribe carries a default and nothing consulted it: every person created
 * got `family` whatever the tribe said. Opening a clan therefore lasted
 * exactly as long as nobody added anybody — the next record added was
 * invisible to the clan again, and no screen would have shown that happening.
 */
class NewPersonPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_person_starts_at_the_tribes_default(): void
    {
        $tribe = Tribe::factory()->create([
            'default_privacy_level' => PrivacyLevel::Clan,
        ]);

        $person = Person::factory()->create([
            'tribe_id' => $tribe->id,
            'privacy_level' => null,
        ]);

        $this->assertSame(PrivacyLevel::Clan, $person->fresh()->privacyLevel());
    }

    public function test_an_explicit_level_is_an_answer_and_is_kept(): void
    {
        // A default that overrode what the caller said would not be a default.
        $tribe = Tribe::factory()->create([
            'default_privacy_level' => PrivacyLevel::Clan,
        ]);

        $person = Person::factory()->create([
            'tribe_id' => $tribe->id,
            'privacy_level' => PrivacyLevel::Private,
        ]);

        $this->assertSame(PrivacyLevel::Private, $person->fresh()->privacyLevel());
    }

    public function test_each_tribe_gets_its_own_answer(): void
    {
        $open = Tribe::factory()->create(['default_privacy_level' => PrivacyLevel::Clan]);
        $closed = Tribe::factory()->create(['default_privacy_level' => PrivacyLevel::Family]);

        $first = Person::factory()->create(['tribe_id' => $open->id, 'privacy_level' => null]);
        $second = Person::factory()->create(['tribe_id' => $closed->id, 'privacy_level' => null]);

        $this->assertSame(PrivacyLevel::Clan, $first->fresh()->privacyLevel());
        $this->assertSame(PrivacyLevel::Family, $second->fresh()->privacyLevel());
    }

    public function test_a_person_belonging_to_no_tribe_falls_back_to_the_configured_default(): void
    {
        $person = Person::factory()->create([
            'tribe_id' => null,
            'privacy_level' => null,
        ]);

        $this->assertSame(
            PrivacyLevel::from(config('genealogy.privacy.default_person_level')),
            $person->fresh()->privacyLevel(),
        );
    }
}
