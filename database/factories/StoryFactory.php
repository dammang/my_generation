<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Story> */
class StoryFactory extends Factory
{
    protected $model = Story::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'summary' => fake()->sentence(16),
            'body' => fake()->paragraphs(4, true),
            'language' => 'en',
            'story_type' => fake()->randomElement(StoryType::cases()),
            'visibility' => PrivacyLevel::Family,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['visibility' => PrivacyLevel::Public]);
    }
}
