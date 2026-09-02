<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EventType> */
class EventTypeFactory extends Factory
{
    protected $model = EventType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = Str::title(fake()->unique()->word());

        return [
            'slug' => Str::slug($label),
            'label' => $label,
            'category' => EventCategory::Other,
            'is_system' => false,
            'sort_order' => 100,
        ];
    }
}
