<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Certainty;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relationship>
 *
 * person_id is always the parent; the inverse row is never created.
 */
class RelationshipFactory extends Factory
{
    protected $model = Relationship::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'related_person_id' => Person::factory(),
            'relationship_type' => RelationshipType::ParentChild,
            'relationship_subtype' => RelationshipSubtype::Biological,
            'is_biological' => true,
            'certainty' => Certainty::Probable,
        ];
    }

    public function parentChild(Person $parent, Person $child): static
    {
        return $this->state(fn () => [
            'person_id' => $parent->id,
            'related_person_id' => $child->id,
        ]);
    }

    public function adoptive(): static
    {
        return $this->state(fn () => [
            'relationship_subtype' => RelationshipSubtype::Adoptive,
            'is_biological' => false,
        ]);
    }

    /** "These two are brothers, we do not know their parents." */
    public function assertedSibling(Person $a, Person $b): static
    {
        return $this->state(fn () => [
            'person_id' => min($a->id, $b->id),
            'related_person_id' => max($a->id, $b->id),
            'relationship_type' => RelationshipType::SiblingAsserted,
            'relationship_subtype' => RelationshipSubtype::Unknown,
            'is_biological' => null,
        ]);
    }
}
