<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventType;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Support\UncertainDateParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonEvent> */
class PersonEventFactory extends Factory
{
    protected $model = PersonEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $parsed = UncertainDateParser::parse((string) fake()->numberBetween(1900, 2020));

        return [
            'person_id' => Person::factory(),
            'event_type_id' => EventType::query()->inRandomOrder()->value('id')
                                        ?? EventType::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'event_date' => $parsed['date'],
            'event_date_precision' => $parsed['precision'],
            'event_date_text' => $parsed['text'],
            'event_year' => $parsed['year'],
        ];
    }

    public function ofType(string $slug): static
    {
        return $this->state(fn () => [
            'event_type_id' => EventType::query()->where('slug', $slug)->value('id'),
        ]);
    }

    public function inYear(int $year): static
    {
        $parsed = UncertainDateParser::parse((string) $year);

        return $this->state(fn () => [
            'event_date' => $parsed['date'],
            'event_date_precision' => $parsed['precision'],
            'event_date_text' => $parsed['text'],
            'event_year' => $parsed['year'],
        ]);
    }
}
