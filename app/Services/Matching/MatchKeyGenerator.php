<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Enums\MatchKeyType;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Blocking keys: the reason duplicate detection is O(n·k) and not O(n²).
 *
 * Two people are only ever compared if they share at least one key. Without
 * this, finding duplicates among a million people means half a trillion
 * comparisons; with it, each person is compared against the handful who share a
 * phonetic name or a name-and-birth-decade.
 *
 * Keys are generated from every recorded spelling, not just the display name —
 * that is what the person_names table is for.
 */
class MatchKeyGenerator
{
    public function __construct(private readonly NameNormaliser $names) {}

    public function regenerateFor(Person $person): int
    {
        $keys = $this->keysFor($person);

        DB::table('person_match_keys')->where('person_id', $person->getKey())->delete();

        if ($keys === []) {
            return 0;
        }

        DB::table('person_match_keys')->insertOrIgnore($keys);

        return count($keys);
    }

    /**
     * @return array<int, array{person_id: int, key_type: string, key_value: string}>
     */
    public function keysFor(Person $person): array
    {
        $spellings = $person->names()->pluck('name')->push($person->display_name)
            ->filter()
            ->unique()
            ->values();

        $keys = [];

        foreach ($spellings as $spelling) {
            $normalised = $this->names->normalise((string) $spelling);

            if ($normalised === '') {
                continue;
            }

            $keys[] = [MatchKeyType::NameNormalized, $normalised];
            $keys[] = [MatchKeyType::NamePhonetic, $this->names->phonetic((string) $spelling)];

            if ($person->birth_year !== null) {
                $keys[] = [MatchKeyType::NameBirthyear, $normalised.'|'.$person->birth_year];
            }

            if ($person->birth_place_id !== null) {
                $keys[] = [MatchKeyType::NamePlace, $normalised.'|'.$person->birth_place_id];
            }
        }

        // A shared parent or spouse is strong evidence even when the spellings
        // differ, so those get their own blocks.
        foreach ($person->parentEdges()->with('person:id,display_name')->get() as $edge) {
            if ($edge->person !== null) {
                $keys[] = [MatchKeyType::ParentName, $this->names->phonetic($edge->person->display_name)];
            }
        }

        foreach ($person->spouses() as $spouse) {
            $keys[] = [MatchKeyType::SpouseName, $this->names->phonetic($spouse->display_name)];
        }

        if ($person->birth_year !== null && $person->birth_place_id !== null) {
            $decade = (int) floor($person->birth_year / 10) * 10;
            $keys[] = [MatchKeyType::BirthDecadePlace, $decade.'|'.$person->birth_place_id];
        }

        return collect($keys)
            ->filter(fn (array $pair) => $pair[1] !== '')
            ->map(fn (array $pair) => [
                'person_id' => $person->getKey(),
                'key_type' => $pair[0]->value,
                'key_value' => mb_substr($pair[1], 0, 120),
            ])
            ->unique(fn (array $row) => $row['key_type'].'|'.$row['key_value'])
            ->values()
            ->all();
    }
}
