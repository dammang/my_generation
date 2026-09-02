<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Merge\MergePeople;
use App\Enums\DuplicateStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MergePeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_super_admin' => true]);
        $this->tribe = Tribe::factory()->create();
    }

    private function person(string $name, ?int $birth = null): Person
    {
        $parts = explode(' ', $name);

        $person = Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? null,
        ]);

        $person->setUncertainDate('birth', $birth === null ? null : (string) $birth)->save();

        return $person->refresh();
    }

    private function merge(Person $winner, Person $loser, array $choices = [])
    {
        return app(MergePeople::class)->handle($this->admin, $winner, $loser, $choices);
    }

    public function test_the_loser_becomes_a_tombstone_rather_than_disappearing(): void
    {
        // Old ULIDs, share links and bookmarks must keep resolving.
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        $this->merge($winner, $loser);

        $this->assertSoftDeleted('people', ['id' => $loser->id]);
        $this->assertSame($winner->id, $loser->fresh()->merged_into_person_id);
    }

    public function test_relationships_move_to_the_winner(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);
        $child = $this->person('Thawng Dam', 1960);

        Relationship::factory()->parentChild($loser, $child)->create();

        $this->merge($winner, $loser);

        $this->assertDatabaseHas('relationships', [
            'person_id' => $winner->id,
            'related_person_id' => $child->id,
        ]);
        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $winner->id,
            'child_id' => $child->id,
        ]);
    }

    public function test_a_duplicated_edge_is_dropped_rather_than_violating_the_unique_key(): void
    {
        // Both records were linked to the same child — the commonest shape of a
        // real duplicate, and a naive repoint would collide.
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);
        $child = $this->person('Thawng Dam', 1960);

        Relationship::factory()->parentChild($winner, $child)->create();
        Relationship::factory()->parentChild($loser, $child)->create();

        $merge = $this->merge($winner, $loser);

        $this->assertSame(1, Relationship::where('related_person_id', $child->id)->count());
        $this->assertSame(1, $merge->moved_records['relationships']['dropped']);
    }

    public function test_an_edge_that_would_become_self_referential_is_dropped(): void
    {
        // Happens when a record was duplicated *and* mislinked as its own parent.
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        Relationship::factory()->parentChild($loser, $winner)->create();

        $this->merge($winner, $loser);

        $this->assertSame(0, Relationship::where('person_id', $winner->id)
            ->where('related_person_id', $winner->id)->count());
    }

    public function test_unions_are_renormalised_when_a_partner_moves(): void
    {
        // partner_1_id < partner_2_id is a CHECK constraint, so a repointed
        // pair has to be reordered, not merely updated.
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);
        $spouse = $this->person('Khoi Dim', 1930);

        Union::factory()->between($loser, $spouse)->create();

        $this->merge($winner, $loser);

        $union = Union::firstOrFail();

        $this->assertSame(min($winner->id, $spouse->id), $union->partner_1_id);
        $this->assertSame(max($winner->id, $spouse->id), $union->partner_2_id);
    }

    public function test_a_union_of_the_winner_with_themselves_is_removed(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        Union::factory()->between($winner, $loser)->create();

        $this->merge($winner, $loser);

        $this->assertSame(0, Union::count(), 'A self-union is an artefact of the merge');
    }

    public function test_the_winner_gains_facts_it_was_missing(): void
    {
        // The point of merging is to end up with the union of what is known.
        $winner = $this->person('Pau Zam');
        $loser = $this->person('Pau Zamm', 1927);
        $loser->update(['biography' => 'Recorded by a second contributor.']);

        $this->merge($winner, $loser);

        $winner->refresh();

        $this->assertSame(1927, $winner->birth_year);
        $this->assertSame('Recorded by a second contributor.', $winner->biography);
    }

    public function test_an_explicit_field_choice_overrides_the_winner(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        $this->merge($winner, $loser, ['birth_year' => 'loser', 'birth_date' => 'loser']);

        $this->assertSame(1927, $winner->fresh()->birth_year);
    }

    public function test_alternate_spellings_survive_the_merge(): void
    {
        // The loser's spelling is exactly the evidence that made them a
        // duplicate; discarding it would lose the reason.
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        $this->merge($winner, $loser);

        $names = PersonName::where('person_id', $winner->id)->pluck('name');

        $this->assertContains('Pau Zamm', $names->all());
    }

    public function test_the_merge_is_fully_logged_for_reversal(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);
        $child = $this->person('Thawng Dam', 1960);
        Relationship::factory()->parentChild($loser, $child)->create();

        $merge = $this->merge($winner, $loser);

        $this->assertSame($loser->display_name, $merge->loser_snapshot['display_name']);
        $this->assertSame(1, $merge->moved_records['relationships']['moved']);
        $this->assertNotNull($merge->merged_at);
    }

    public function test_it_closes_the_duplicate_candidate(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        DB::table('duplicate_candidates')->insert([
            'ulid' => (string) Str::ulid(),
            'person_a_id' => min($winner->id, $loser->id),
            'person_b_id' => max($winner->id, $loser->id),
            'score' => 0.91,
            'status' => DuplicateStatus::Open->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->merge($winner, $loser);

        $this->assertSame(
            DuplicateStatus::Merged->value,
            DB::table('duplicate_candidates')->value('status')
        );
    }

    public function test_a_merge_writes_a_revision_and_an_audit_entry(): void
    {
        $winner = $this->person('Pau Zam', 1926);
        $loser = $this->person('Pau Zamm', 1927);

        $this->merge($winner, $loser);

        $this->assertDatabaseHas('revisions', [
            'revisionable_id' => $winner->id,
            'action' => 'merged',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'person.merged']);
    }

    public function test_a_person_cannot_be_merged_into_themselves(): void
    {
        $person = $this->person('Pau Zam', 1926);

        $this->expectException(GenealogyRuleException::class);

        $this->merge($person, $person);
    }
}
