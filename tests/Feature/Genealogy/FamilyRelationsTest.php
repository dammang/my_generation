<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Enums\RelationshipSubtype;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Union;
use App\Models\UnionChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spouses and siblings are derived, never stored. These tests pin the
 * derivation, because getting it wrong is invisible until a family tree renders
 * somebody's aunt as their sister.
 */
class FamilyRelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{father: Person, mother: Person, children: array<int, Person>, union: Union} */
    private function nuclearFamily(int $childCount = 3): array
    {
        $father = Person::factory()->male()->bornExactly(1898)->create();
        $mother = Person::factory()->female()->bornExactly(1902)->create();
        $union = Union::factory()->between($father, $mother)->create();

        $children = [];

        foreach (range(1, $childCount) as $i) {
            $child = Person::factory()->bornExactly(1920 + $i)->create();

            UnionChild::create(['union_id' => $union->id, 'person_id' => $child->id]);

            foreach ([$father, $mother] as $parent) {
                Relationship::factory()->parentChild($parent, $child)->create();
            }

            $children[] = $child;
        }

        return ['father' => $father, 'mother' => $mother, 'children' => $children, 'union' => $union];
    }

    public function test_a_child_has_both_parents(): void
    {
        ['father' => $father, 'mother' => $mother, 'children' => $children] = $this->nuclearFamily();

        $parents = $children[0]->parents()->pluck('people.id');

        $this->assertCount(2, $parents);
        $this->assertContains($father->id, $parents->all());
        $this->assertContains($mother->id, $parents->all());
    }

    public function test_a_parent_has_every_child(): void
    {
        ['father' => $father, 'children' => $children] = $this->nuclearFamily();

        $this->assertCount(3, $father->children()->get());
    }

    public function test_the_inverse_relationship_row_is_never_stored(): void
    {
        // Direction is canonical: two parents times three children is six rows,
        // not twelve.
        $this->nuclearFamily();

        $this->assertSame(6, Relationship::count());
    }

    public function test_siblings_are_derived_from_shared_parents(): void
    {
        ['children' => $children] = $this->nuclearFamily();

        $siblings = $children[0]->siblings()->pluck('id');

        $this->assertCount(2, $siblings);
        $this->assertContains($children[1]->id, $siblings->all());
        $this->assertNotContains($children[0]->id, $siblings->all(), 'A person is not their own sibling');
    }

    public function test_no_sibling_rows_are_written(): void
    {
        // Storing sibship would be O(n^2) per family and would drift on edits.
        $this->nuclearFamily();

        $this->assertSame(
            0,
            Relationship::where('relationship_type', 'sibling_asserted')->count()
        );
    }

    public function test_half_siblings_are_distinguished_from_full_siblings(): void
    {
        ['father' => $father, 'children' => $children] = $this->nuclearFamily(2);

        $secondWife = Person::factory()->female()->create();
        $halfSibling = Person::factory()->create();
        Relationship::factory()->parentChild($father, $halfSibling)->create();
        Relationship::factory()->parentChild($secondWife, $halfSibling)->create();

        $this->assertCount(2, $children[0]->siblings());
        $this->assertCount(1, $children[0]->siblings(fullOnly: true));
    }

    public function test_a_person_with_unknown_parents_has_no_siblings(): void
    {
        $orphan = Person::factory()->create();

        $this->assertCount(0, $orphan->siblings());
    }

    public function test_spouses_are_derived_from_unions(): void
    {
        ['father' => $father, 'mother' => $mother] = $this->nuclearFamily();

        $spouses = $father->spouses();

        $this->assertCount(1, $spouses);
        $this->assertSame($mother->id, $spouses->first()->id);
    }

    public function test_a_person_can_have_several_marriages(): void
    {
        $person = Person::factory()->male()->create();
        [$first, $second] = Person::factory()->count(2)->female()->create()->all();

        Union::factory()->between($person, $first)->create();
        Union::factory()->between($person, $second)->create();

        $this->assertCount(2, $person->spouses());
        $this->assertSame([1, 2], $person->allUnions()->pluck('order_index')->all());
    }

    public function test_a_single_parent_union_has_no_spouse(): void
    {
        $parent = Person::factory()->create();
        Union::factory()->singleParent($parent)->create();

        $this->assertCount(0, $parent->spouses());
    }

    public function test_a_union_lists_its_children_in_birth_order(): void
    {
        ['union' => $union, 'children' => $children] = $this->nuclearFamily();

        $ordered = $union->children()->get()->pluck('id')->all();

        $this->assertSame(array_map(fn (Person $c) => $c->id, $children), $ordered);
    }

    public function test_an_adoptive_parent_is_still_a_parent(): void
    {
        // Adoption is a subtype on the edge, not a separate structure, so an
        // adopted child appears in the tree exactly like any other.
        $parent = Person::factory()->create();
        $child = Person::factory()->create();

        Relationship::factory()->parentChild($parent, $child)->adoptive()->create();

        $parents = $child->parents()->get();

        $this->assertCount(1, $parents);
        $this->assertSame(
            RelationshipSubtype::Adoptive->value,
            $parents->first()->pivot->relationship_subtype
        );
    }

    public function test_a_soft_deleted_relationship_leaves_the_family(): void
    {
        ['father' => $father, 'children' => $children] = $this->nuclearFamily();

        Relationship::query()
            ->where('person_id', $father->id)
            ->where('related_person_id', $children[0]->id)
            ->firstOrFail()
            ->delete();

        $this->assertCount(1, $children[0]->parents()->get());
        $this->assertCount(2, $father->children()->get());
    }
}
