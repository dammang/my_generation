<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tribe> */
class TribeFactory extends Factory
{
    protected $model = Tribe::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();
        $name = Str::title($name);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'native_name' => $name,
            'short_name' => Str::upper(Str::substr($name, 0, 3)),
            'description' => fake()->sentence(12),
            'country_code' => 'MM',
            'region' => fake()->city(),
            'default_privacy_level' => PrivacyLevel::Tribe,
            'status' => RecordStatus::Active,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['name' => $name, 'slug' => Str::slug($name), 'native_name' => $name]);
    }
}
