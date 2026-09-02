<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PrivacyLevel;
use App\Enums\SourceReliability;
use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Source> */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'source_type' => fake()->randomElement(SourceType::cases()),
            'description' => fake()->sentence(14),
            'reliability' => SourceReliability::Secondary,
            'privacy_level' => PrivacyLevel::Tribe,
            'publication_year' => fake()->optional()->numberBetween(1900, 2020),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['reliability' => SourceReliability::Primary]);
    }

    public function ofType(SourceType $type): static
    {
        return $this->state(fn () => ['source_type' => $type]);
    }
}
