<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * A starting gazetteer.
 *
 * Deliberately small: places are contributed by users, and pre-loading a world
 * gazetteer would bury the handful that matter to the first tribes. What is
 * seeded here is the countries a Zomi/Chin diaspora actually spans, plus the
 * township-and-village layer the demo tree needs.
 *
 * Idempotent on (parent, name, type). `depth` and the materialised `path` are
 * maintained by PlaceObserver.
 */
class PlaceSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const COUNTRIES = [
        'MM' => ['Myanmar'],
        'IN' => ['India'],
        'MY' => ['Malaysia'],
        'SG' => ['Singapore'],
        'US' => ['United States'],
        'AU' => ['Australia'],
        'GB' => ['United Kingdom'],
        'NZ' => ['New Zealand'],
    ];

    /** Township => villages, all within Chin State, Myanmar. */
    private const CHIN_TOWNSHIPS = [
        'Tedim' => ['Tedim', 'Saizang', 'Suangpi', 'Lamzang', 'Mualbem', 'Khuasak', 'Thuklai', 'Vangteh'],
        'Tonzang' => ['Tonzang', 'Cikha', 'Buanman', 'Lailui'],
        'Falam' => ['Falam', 'Rihkhawdar'],
        'Hakha' => ['Hakha'],
    ];

    public function run(): void
    {
        $countries = [];
        foreach (self::COUNTRIES as $code => $names) {
            $countries[$code] = $this->place($names[0], 'country', null, $code);
        }

        $chin = $this->place('Chin State', 'state', $countries['MM'], 'MM');

        foreach (self::CHIN_TOWNSHIPS as $township => $villages) {
            $t = $this->place($township, 'township', $chin, 'MM');

            foreach ($villages as $village) {
                $this->place($village, 'village', $t, 'MM');
            }
        }

        // Diaspora destinations that appear in migration events.
        $this->place('Yangon', 'city', $countries['MM'], 'MM');
        $this->place('Kalay', 'town', $countries['MM'], 'MM');
        $this->place('Kuala Lumpur', 'city', $countries['MY'], 'MY');
        $this->place('Mizoram', 'state', $countries['IN'], 'IN');
        $this->place('Indianapolis', 'city', $countries['US'], 'US');
        $this->place('Tulsa', 'city', $countries['US'], 'US');

        $this->command?->info('Seeded '.Place::count().' places.');
    }

    private function place(string $name, string $type, ?Place $parent, string $countryCode): Place
    {
        // depth and path are maintained by PlaceObserver.
        return Place::firstOrCreate(
            [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parent?->id,
            ],
            ['country_code' => $countryCode],
        );
    }
}
