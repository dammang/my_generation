<?php

declare(strict_types=1);

namespace App\Services\Matching;

/**
 * Turns a written name into comparable keys.
 *
 * Metaphone alone is tuned for English. Tedim and Zomi orthography writes the
 * same name as "Thawng", "Thawngdam", "Thawng Dham" and "Tawng" — aspiration is
 * inconsistently transcribed, and vowel doubling is a spelling preference
 * rather than a distinction. The transliteration ruleset runs first so those
 * collapse to one key; it lives in config so it can be tuned per language
 * without a deploy.
 */
class NameNormaliser
{
    /** Lowercased, stripped of everything that is not a letter or digit. */
    public function normalise(string $name): string
    {
        $folded = mb_strtolower(trim($name));

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $folded);
    }

    /** Applies the configured orthographic rules, then a phonetic encoding. */
    public function phonetic(string $name, string $ruleset = 'default'): string
    {
        $transliterated = $this->transliterate($this->normalise($name), $ruleset);

        // Metaphone on an ASCII-folded, rule-normalised string. Anything the
        // encoder cannot represent falls back to the transliteration itself,
        // which is still far better than comparing raw spellings.
        $ascii = (string) preg_replace('/[^a-z0-9]/', '', $transliterated);

        return $ascii === '' ? $transliterated : (metaphone($ascii) ?: $ascii);
    }

    public function transliterate(string $normalised, string $ruleset = 'default'): string
    {
        $rules = config("genealogy.matching.transliteration.{$ruleset}", []);

        // Longest patterns first, so "th" is not consumed by a "t" rule.
        uksort($rules, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return str_replace(array_keys($rules), array_values($rules), $normalised);
    }

    /**
     * Jaro-Winkler, which favours strings agreeing at the start — the right
     * bias for names, where prefixes are stable and endings vary.
     */
    public function similarity(string $a, string $b): float
    {
        $a = $this->normalise($a);
        $b = $this->normalise($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $jaro = $this->jaro($a, $b);

        $prefix = 0;
        $limit = min(4, min(strlen($a), strlen($b)));

        for ($i = 0; $i < $limit && $a[$i] === $b[$i]; $i++) {
            $prefix++;
        }

        return $jaro + ($prefix * 0.1 * (1 - $jaro));
    }

    private function jaro(string $a, string $b): float
    {
        $lenA = strlen($a);
        $lenB = strlen($b);
        $window = max(0, (int) floor(max($lenA, $lenB) / 2) - 1);

        $matchesA = array_fill(0, $lenA, false);
        $matchesB = array_fill(0, $lenB, false);
        $matches = 0;

        for ($i = 0; $i < $lenA; $i++) {
            $start = max(0, $i - $window);
            $end = min($i + $window + 1, $lenB);

            for ($j = $start; $j < $end; $j++) {
                if ($matchesB[$j] || $a[$i] !== $b[$j]) {
                    continue;
                }

                $matchesA[$i] = true;
                $matchesB[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;

        for ($i = 0; $i < $lenA; $i++) {
            if (! $matchesA[$i]) {
                continue;
            }

            while (! $matchesB[$k]) {
                $k++;
            }

            if ($a[$i] !== $b[$k]) {
                $transpositions++;
            }

            $k++;
        }

        return ($matches / $lenA + $matches / $lenB + ($matches - $transpositions / 2) / $matches) / 3;
    }
}
