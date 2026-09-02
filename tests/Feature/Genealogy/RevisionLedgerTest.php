<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Enums\RevisionAction;
use App\Models\Person;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_person_records_a_created_revision(): void
    {
        $person = Person::factory()->create();

        $revision = Revision::where('revisionable_type', 'person')
            ->where('revisionable_id', $person->id)
            ->firstOrFail();

        $this->assertSame(RevisionAction::Created, $revision->action);
        $this->assertNull($revision->field);
    }

    public function test_changing_a_birth_year_records_both_values(): void
    {
        // The motivating case from the architecture: 1921 corrected to 1923.
        $person = Person::factory()->bornExactly(1921)->create();

        $person->setUncertainDate('birth', '1923');
        $person->save();

        $revision = Revision::where('revisionable_id', $person->id)
            ->where('field', 'birth_date')
            ->firstOrFail();

        $this->assertSame(RevisionAction::Updated, $revision->action);
        $this->assertSame('1921-01-01 00:00:00', $revision->old_value);
        $this->assertSame('1923-01-01 00:00:00', $revision->new_value);
    }

    public function test_it_records_one_revision_per_changed_field(): void
    {
        // Names are set explicitly: the factory draws from a pool that includes
        // real Tedim names, so updating to one of them can be a no-op — which
        // correctly writes no revision, and would make this test flaky.
        $person = Person::factory()->create(['first_name' => 'Aaa', 'last_name' => 'Bbb']);
        Revision::query()->delete();

        $person->update(['first_name' => 'Ccc', 'last_name' => 'Ddd']);

        $fields = Revision::where('revisionable_id', $person->id)->pluck('field');

        $this->assertTrue($fields->contains('first_name'));
        $this->assertTrue($fields->contains('last_name'));
    }

    public function test_it_does_not_record_unaudited_fields(): void
    {
        // Derived years and cache flags are not genealogical claims; recording
        // them would bury the real history.
        $person = Person::factory()->create(['has_open_dispute' => false]);
        Revision::query()->delete();

        $person->update(['has_open_dispute' => true]);

        $this->assertSame(0, Revision::where('revisionable_id', $person->id)->count());
    }

    public function test_a_revision_records_who_made_the_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $person = Person::factory()->create();

        $this->assertSame(
            $user->id,
            Revision::where('revisionable_id', $person->id)->value('changed_by')
        );
    }

    public function test_revision_context_attaches_a_reason_and_source(): void
    {
        $person = Person::factory()->create(['first_name' => 'Aaa']);
        Revision::query()->delete();

        $person->withRevisionContext(reason: 'Baptism register, entry 114')
            ->update(['first_name' => 'Ccc']);

        $this->assertSame(
            'Baptism register, entry 114',
            Revision::where('revisionable_id', $person->id)->value('reason')
        );
    }

    public function test_soft_deleting_records_a_deleted_revision(): void
    {
        $person = Person::factory()->create();
        $person->delete();

        $this->assertTrue(
            Revision::where('revisionable_id', $person->id)
                ->where('action', RevisionAction::Deleted)
                ->exists()
        );
    }

    public function test_revisions_can_be_suspended_for_bulk_work(): void
    {
        Person::withoutRevisions(function (): void {
            Person::factory()->count(3)->create();
        });

        $this->assertSame(0, Revision::count());
    }

    public function test_suspension_is_restored_even_if_the_callback_throws(): void
    {
        try {
            Person::withoutRevisions(function (): void {
                throw new \RuntimeException('import failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        Person::factory()->create();

        $this->assertGreaterThan(0, Revision::count());
    }
}
