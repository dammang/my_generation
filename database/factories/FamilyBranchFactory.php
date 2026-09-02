<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\FamilyBranch;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FamilyBranch> */
class FamilyBranchFactory extends Factory
{
    protected $model = FamilyBranch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->firstName().' Family';

        return [
            'tribe_id' => Tribe::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(10),
            'status' => RecordStatus::Active,
        ];
    }
}
