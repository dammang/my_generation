<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Enums\DatePrecision;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_gets_a_public_ulid_and_is_routed_by_it(): void
    {
        $person = Person::factory()->create();

        $this->assertSame(26, strlen($person->ulid));
        $this->assertSame('ulid', $person->getRouteKeyName());
    }

    public function test_uncertain_dates_round_trip_through_the_database(): void
    {
        $person = Person::factory()->create();
        $person->setUncertainDate('birth', 'abt. 1902')->save();

        $fresh = $person->fresh();

        $this->assertSame(DatePrecision::About, $fresh->birth_date_precision);
        $this->assertSame(1902, $fresh->birth_year);
        $this->assertSame('abt. 1902', $fresh->birth_date_text);
        $this->assertSame('abt. 1902', $fresh->dateFact('birth')->display());
    }

    public function test_the_derived_year_is_kept_in_sync_on_save(): void
    {
        // Every range query and every duplicate comparison uses _year, so it
        // must never drift from _date.
        $person = Person::factory()->create();

        $person->birth_date = '1926-04-02';
        $person->save();

        $this->assertSame(1926, $person->fresh()->birth_year);
    }

    public function test_a_person_with_no_dates_is_treated_as_living(): void
    {
        // Fail closed: no dates means strictest privacy handling.
        $person = Person::factory()->undated()->create();

        $this->assertFalse($person->isDeceased());
    }

    public function test_someone_born_beyond_the_maximum_age_is_treated_as_deceased(): void
    {
        config()->set('genealogy.living.max_age', 110);

        $person = Person::factory()->bornExactly(1850)->create();

        $this->assertTrue($person->isDeceased());
    }

    public function test_a_death_record_makes_a_person_deceased(): void
    {
        // bornExactly, not bornAround: bornAround is deliberately imprecise and
        // may store 1920 for 1926 when it picks a decade reading.
        $person = Person::factory()->bornExactly(1926)->deceased(1994)->create();

        $this->assertTrue($person->isDeceased());
        $this->assertFalse($person->is_living);
        $this->assertSame('1926–1994', $person->lifespan());
    }

    public function test_a_recently_born_person_is_a_minor(): void
    {
        $child = Person::factory()->bornExactly((int) date('Y') - 8)->create();
        $adult = Person::factory()->bornExactly(1980)->create();

        $this->assertTrue($child->isMinor());
        $this->assertFalse($adult->isMinor());
    }

    public function test_the_factory_produces_imprecise_dates(): void
    {
        // A factory that always produced exact ISO dates would let precision
        // bugs through the whole suite.
        $precisions = Person::factory()->count(60)->create()
            ->pluck('birth_date_precision')
            ->unique();

        $this->assertGreaterThan(1, $precisions->count(), 'Expected varied date precisions');
    }
}
