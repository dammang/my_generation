<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Enums\PersonNameType;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\User;
use App\Services\Statistics\ContributionCounter;
use Illuminate\Support\Facades\DB;

/**
 * Creates a person and their primary name row.
 *
 * The name row is not optional bookkeeping: person_names is what makes
 * "Thawng Dam", "Thawngdam" and "Thawng Dham" resolve to one ancestor, and a
 * person created without one is invisible to both search and duplicate
 * detection until somebody notices.
 */
class CreatePerson
{
    public function __construct(private readonly ContributionCounter $contributions) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(User $author, array $attributes): Person
    {
        return DB::transaction(function () use ($author, $attributes): Person {
            $dates = [
                'birth' => $attributes['birth'] ?? null,
                'death' => $attributes['death'] ?? null,
            ];

            // Set explicitly rather than mass-assigned: the public identifier
            // is not something a stray request field should be able to choose.
            // A client may supply one so a person created offline is referable
            // before the server has ever seen them; HasUlid only mints one when
            // the attribute is empty.
            $ulid = $attributes['ulid'] ?? null;
            unset($attributes['birth'], $attributes['death'], $attributes['ulid']);

            $person = new Person($attributes);
            $person->created_by = $author->getKey();

            if (is_string($ulid) && $ulid !== '') {
                $person->ulid = $ulid;
            }

            foreach ($dates as $prefix => $expression) {
                if ($expression !== null) {
                    $person->setUncertainDate($prefix, (string) $expression);
                }
            }

            $person->save();

            PersonName::firstOrCreate(
                [
                    'person_id' => $person->getKey(),
                    'normalized' => $this->normalize($person->display_name),
                    'type' => PersonNameType::Birth,
                ],
                [
                    'name' => $person->display_name,
                    'is_primary' => true,
                    'created_by' => $author->getKey(),
                ],
            );

            $this->contributions->increment($author, 'people_added');

            return $person;
        });
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name);
    }
}
