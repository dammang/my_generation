<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PersonNameType;
use App\Models\Person;
use App\Models\PersonName;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonName> */
class PersonNameFactory extends Factory
{
    protected $model = PersonName::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->firstName().' '.fake()->lastName();

        return [
            'person_id' => Person::factory(),
            'name' => $name,
            'normalized' => self::normalize($name),
            'type' => PersonNameType::Alternate,
        ];
    }

    public function spelled(string $name, PersonNameType $type = PersonNameType::Alternate): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'normalized' => self::normalize($name),
            'type' => $type,
        ]);
    }

    /** Placeholder normalisation; MatchKeyGenerator owns the real rules in Phase 6. */
    private static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name);
    }
}
