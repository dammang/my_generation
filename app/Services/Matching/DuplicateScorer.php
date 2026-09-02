<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Models\Person;

/**
 * Scores how likely two records are the same person.
 *
 * Every feature's contribution is returned alongside the score. A reviewer
 * deciding whether to merge two ancestors needs to see *why* the system thinks
 * they match — "0.87" is not evidence, "same phonetic name, birth years two
 * apart, same village, shared father" is.
 *
 * Nothing merges automatically, at any score.
 */
class DuplicateScorer
{
    public function __construct(private readonly NameNormaliser $names) {}

    /**
     * @return array{score: float, signals: array<string, mixed>}
     */
    public function score(Person $a, Person $b): array
    {
        $weights = config('genealogy.matching.weights');
        $tolerance = (int) config('genealogy.matching.birth_year_tolerance');

        $signals = [];
        $achieved = 0.0;

        // Only features both records can actually be judged on count toward the
        // total. Scoring against the full weight set punishes missing data, and
        // the commonest real duplicate is precisely a record a second
        // contributor added with no relatives attached yet — it would never
        // clear the threshold no matter how exactly the name and dates agree.
        $applicable = 0.0;

        $similarity = $this->bestNameSimilarity($a, $b);
        $achieved += $similarity * $weights['name_similarity'];
        $applicable += $weights['name_similarity'];
        $signals['name_similarity'] = round($similarity, 3);

        $phoneticMatch = $this->sharePhoneticName($a, $b);
        $achieved += $phoneticMatch ? $weights['name_phonetic'] : 0.0;
        $applicable += $weights['name_phonetic'];
        $signals['name_phonetic'] = $phoneticMatch;

        foreach (['birth' => 'birth_year', 'death' => 'death_year'] as $label => $column) {
            if ($a->{$column} === null || $b->{$column} === null) {
                $signals["{$label}_year"] = null;   // not comparable

                continue;
            }

            $result = $this->compareYears($a->{$column}, $b->{$column}, $tolerance);
            $achieved += $result * $weights["{$label}_year"];
            $applicable += $weights["{$label}_year"];
            $signals["{$label}_year"] = round($result, 2);
        }

        if ($a->birth_place_id !== null && $b->birth_place_id !== null) {
            $samePlace = $a->birth_place_id === $b->birth_place_id;
            $achieved += $samePlace ? $weights['birth_place'] : 0.0;
            $applicable += $weights['birth_place'];
            $signals['birth_place'] = $samePlace;
        } else {
            $signals['birth_place'] = null;
        }

        $parentsA = $a->parentEdges()->pluck('person_id');
        $parentsB = $b->parentEdges()->pluck('person_id');

        if ($parentsA->isNotEmpty() && $parentsB->isNotEmpty()) {
            $sharedParent = $this->shareA($parentsA, $parentsB);
            $achieved += $sharedParent ? $weights['shared_parent'] : 0.0;
            $applicable += $weights['shared_parent'];
            $signals['shared_parent'] = $sharedParent;
        } else {
            $signals['shared_parent'] = null;
        }

        $spousesA = $a->spouses()->pluck('id');
        $spousesB = $b->spouses()->pluck('id');

        if ($spousesA->isNotEmpty() && $spousesB->isNotEmpty()) {
            $sharedSpouse = $this->shareA($spousesA, $spousesB);
            $achieved += $sharedSpouse ? $weights['shared_spouse'] : 0.0;
            $applicable += $weights['shared_spouse'];
            $signals['shared_spouse'] = $sharedSpouse;
        } else {
            $signals['shared_spouse'] = null;
        }

        $score = $applicable > 0.0 ? $achieved / $applicable : 0.0;

        // Contradictory dates are evidence against, not merely absent evidence.
        // Without this, two different people sharing a common name drift over
        // the threshold on name agreement alone.
        if ($this->contradictoryYears($a, $b, $tolerance)) {
            $score -= 0.35;
            $signals['contradictory_dates'] = true;
        }

        // A pair judged on almost nothing is not a strong match however well
        // the little that was comparable agreed.
        $signals['evidence_weight'] = round($applicable, 2);

        if ($applicable < 0.5) {
            $score *= $applicable / 0.5;
        }

        return ['score' => max(0.0, min(1.0, round($score, 3))), 'signals' => $signals];
    }

    private function bestNameSimilarity(Person $a, Person $b): float
    {
        $best = 0.0;

        foreach ($this->spellings($a) as $left) {
            foreach ($this->spellings($b) as $right) {
                $best = max($best, $this->names->similarity($left, $right));
            }
        }

        return $best;
    }

    private function sharePhoneticName(Person $a, Person $b): bool
    {
        $left = collect($this->spellings($a))->map(fn (string $n) => $this->names->phonetic($n));
        $right = collect($this->spellings($b))->map(fn (string $n) => $this->names->phonetic($n));

        return $left->intersect($right)->isNotEmpty();
    }

    /** @return array<int, string> */
    private function spellings(Person $person): array
    {
        return $person->names->pluck('name')
            ->push($person->display_name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** 1.0 for an exact match, tapering to 0 at the tolerance boundary. */
    private function compareYears(?int $a, ?int $b, int $tolerance): float
    {
        if ($a === null || $b === null) {
            return 0.0;
        }

        $gap = abs($a - $b);

        return $gap > $tolerance ? 0.0 : 1.0 - ($gap / ($tolerance + 1));
    }

    private function contradictoryYears(Person $a, Person $b, int $tolerance): bool
    {
        foreach (['birth_year', 'death_year'] as $column) {
            if ($a->{$column} !== null && $b->{$column} !== null
                && abs($a->{$column} - $b->{$column}) > $tolerance * 3) {
                return true;
            }
        }

        return false;
    }

    private function shareA($left, $right): bool
    {
        return collect($left)->intersect(collect($right))->isNotEmpty();
    }
}
