<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use App\Support\NameCorpus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Place> */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(NameCorpus::PLACES),
            'type' => 'village',
            'country_code' => 'MM',
            'depth' => 0,
            'path' => '',
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function under(Place $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'path' => $parent->path,   // completed by the observer in Phase 3
        ]);
    }
}
