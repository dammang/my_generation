<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Clan;
use App\Models\Tribe;
use App\Support\NameCorpus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Clan> */
class ClanFactory extends Factory
{
    protected $model = Clan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement(NameCorpus::FAMILY);

        return [
            'tribe_id' => Tribe::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'native_name' => $name,
            'depth' => 0,
            'level_label' => 'Clan',
            'status' => RecordStatus::Active,
        ];
    }

    /** A sub-clan one level below the given clan — depth is data, not schema. */
    public function under(Clan $parent, string $levelLabel = 'Sub-clan'): static
    {
        return $this->state(fn () => [
            'tribe_id' => $parent->tribe_id,
            'parent_clan_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'level_label' => $levelLabel,
        ]);
    }
}
