<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Generation;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    protected $model = Generation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 20);

        return [
            'tribe_id' => Tribe::factory(),
            'generation_number' => $number,
            'generation_name' => self::ordinal($number).' Generation',
        ];
    }

    public function number(int $n): static
    {
        return $this->state(fn () => [
            'generation_number' => $n,
            'generation_name' => self::ordinal($n).' Generation',
        ]);
    }

    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }
}
