<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Models\Person;
use App\Models\Union;
use App\Support\UncertainDateParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Union> */
class UnionFactory extends Factory
{
    protected $model = Union::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'union_type' => UnionType::Marriage,
            'status' => UnionStatus::Unknown,
            'order_index' => 1,
        ];
    }

    /**
     * The pair, normalised so partner_1_id is always the lower id. The CHECK
     * constraint enforces this at the database level; doing it here keeps the
     * factory from tripping over it.
     */
    public function between(Person $a, Person $b): static
    {
        return $this->state(fn () => [
            'partner_1_id' => min($a->id, $b->id),
            'partner_2_id' => max($a->id, $b->id),
        ]);
    }

    /** Single-parent families are real and common in historical records. */
    public function singleParent(Person $parent): static
    {
        return $this->state(fn () => [
            'partner_1_id' => $parent->id,
            'partner_2_id' => null,
            'union_type' => UnionType::Unknown,
        ]);
    }

    public function marriedIn(int $year): static
    {
        $parsed = UncertainDateParser::parse((string) $year);

        return $this->state(fn () => [
            'marriage_date' => $parsed['date'],
            'marriage_date_precision' => $parsed['precision'],
            'marriage_date_text' => $parsed['text'],
            'marriage_year' => $parsed['year'],
        ]);
    }
}
