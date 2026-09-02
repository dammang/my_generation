<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\Gender;
use App\Models\Person;
use App\Models\Union;

/**
 * Plausibility checks that never block a write.
 *
 * Every threshold is configurable in config/genealogy.php, because what counts
 * as implausible differs by era and community — a marriage age that is alarming
 * in one record set is unremarkable in another two centuries earlier.
 */
class GenealogyWarnings
{
    /** @return array<int, GenealogyWarning> */
    public function forPerson(Person $person): array
    {
        $warnings = [];
        $birth = $person->birth_year;
        $death = $person->death_year;

        if ($birth !== null && $death !== null) {
            if ($death < $birth) {
                $warnings[] = new GenealogyWarning(
                    'DEATH_BEFORE_BIRTH',
                    "Recorded death ({$death}) is before recorded birth ({$birth}). Please verify.",
                    'death_date',
                );
            } elseif ($death - $birth > $this->max('max_lifespan')) {
                $warnings[] = new GenealogyWarning(
                    'IMPLAUSIBLE_LIFESPAN',
                    'That would be an age of '.($death - $birth).' years. Please verify.',
                    'death_date',
                );
            }
        }

        if ($birth !== null && $birth > (int) date('Y')) {
            $warnings[] = new GenealogyWarning(
                'BIRTH_IN_FUTURE',
                "A birth year of {$birth} is in the future. Please verify.",
                'birth_date',
            );
        }

        return $warnings;
    }

    /**
     * Checks on a proposed parent-child edge. All soft: a posthumous birth is
     * real, a very young parent is real, and a record that looks impossible is
     * often a transcription error worth keeping and flagging.
     *
     * @return array<int, GenealogyWarning>
     */
    public function forParentChild(Person $parent, Person $child): array
    {
        $warnings = [];
        $parentBirth = $parent->birth_year;
        $parentDeath = $parent->death_year;
        $childBirth = $child->birth_year;

        if ($parentBirth === null || $childBirth === null) {
            return $warnings;
        }

        $ageAtBirth = $childBirth - $parentBirth;

        if ($ageAtBirth < 0) {
            $warnings[] = new GenealogyWarning(
                'CHILD_BORN_BEFORE_PARENT',
                "{$child->display_name} was born before {$parent->display_name}. Please verify.",
                'birth_date',
            );
        } elseif ($ageAtBirth < $this->max('min_parent_age')) {
            $warnings[] = new GenealogyWarning(
                'PARENT_AGE_LOW',
                "{$parent->display_name} would have been {$ageAtBirth}. Please verify.",
                'birth_date',
            );
        } else {
            $limit = $parent->gender === Gender::Female
                ? $this->max('max_mother_age')
                : $this->max('max_father_age');

            if ($ageAtBirth > $limit) {
                $warnings[] = new GenealogyWarning(
                    'PARENT_AGE_HIGH',
                    "{$parent->display_name} would have been {$ageAtBirth}. Please verify.",
                    'birth_date',
                );
            }
        }

        // A child born shortly after the father's death is ordinary; a child
        // born years afterwards is not.
        if ($parentDeath !== null && $childBirth > $parentDeath) {
            $graceYears = $parent->gender === Gender::Male
                ? (int) ceil($this->max('posthumous_birth_months') / 12)
                : 0;

            if ($childBirth - $parentDeath > $graceYears) {
                $warnings[] = new GenealogyWarning(
                    'CHILD_BORN_AFTER_PARENT_DEATH',
                    "Born {$childBirth}, ".($childBirth - $parentDeath)
                        ." years after {$parent->display_name}'s recorded death. Please verify.",
                    'birth_date',
                );
            }
        }

        return $warnings;
    }

    /** @return array<int, GenealogyWarning> */
    public function forUnion(Union $union, ?Person $partner1, ?Person $partner2): array
    {
        $warnings = [];
        $year = $union->marriage_year;

        if ($year === null) {
            return $warnings;
        }

        foreach (array_filter([$partner1, $partner2]) as $partner) {
            if ($partner->birth_year !== null) {
                $age = $year - $partner->birth_year;

                if ($age < 0) {
                    $warnings[] = new GenealogyWarning(
                        'MARRIAGE_BEFORE_BIRTH',
                        "{$partner->display_name} was not born until {$partner->birth_year}. Please verify.",
                        'marriage_date',
                    );
                } elseif ($age < $this->max('min_marriage_age')) {
                    $warnings[] = new GenealogyWarning(
                        'MARRIAGE_AGE_LOW',
                        "{$partner->display_name} would have been {$age}. Please verify.",
                        'marriage_date',
                    );
                }
            }

            if ($partner->death_year !== null && $year > $partner->death_year) {
                $warnings[] = new GenealogyWarning(
                    'MARRIAGE_AFTER_DEATH',
                    "{$partner->display_name} died in {$partner->death_year}. Please verify.",
                    'marriage_date',
                );
            }
        }

        return $warnings;
    }

    private function max(string $key): int
    {
        return (int) config("genealogy.warnings.{$key}");
    }
}
