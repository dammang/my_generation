<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DatePrecision;
use Carbon\CarbonImmutable;

/**
 * Turns whatever a contributor typed into the four-column storage pattern.
 *
 * Genealogy input is rarely an ISO date. "abt. 1902", "1920s", "before the war",
 * "1932-05" and "14 May 1932" all have to survive, and the original wording is
 * always kept in _date_text because it is the primary evidence.
 */
final class UncertainDateParser
{
    /**
     * @return array{date: ?string, end: ?string, precision: string, text: ?string, year: ?int}
     */
    public static function parse(?string $input, ?string $precisionHint = null): array
    {
        $text = $input === null ? null : trim($input);

        if ($text === null || $text === '') {
            return self::result(null, null, DatePrecision::Unknown, null, null);
        }

        $normalized = mb_strtolower($text);

        // Explicit qualifier prefixes. A list of pairs rather than a keyed
        // array, because PHP array keys cannot be enum cases.
        // Longest alternative first — "bef" would otherwise match inside
        // "before" and leave "ore 1940" behind. The (?=\d) lookahead means a
        // qualifier is only stripped when something numeric follows, so
        // "before the war" stays unparsed and keeps its wording instead.
        $qualifiers = [
            [DatePrecision::About,  '/^(?:about|abt\.?|circa|ca\.?|c\.)\s*(?=\d)/u'],
            [DatePrecision::Before, '/^(?:before|bef\.?|pre-?)\s*(?=\d)/u'],
            [DatePrecision::After,  '/^(?:after|aft\.?|post-?)\s*(?=\d)/u'],
        ];

        $precision = null;
        foreach ($qualifiers as [$case, $pattern]) {
            if (preg_match($pattern, $normalized)) {
                $precision = $case;
                $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
                break;
            }
        }

        // Ranges: "1920-1925", "1920 to 1925", "between 1920 and 1925"
        if (preg_match('/^(?:between\s+)?(\d{3,4})\s*(?:-|–|to|and)\s*(\d{3,4})$/u', trim($normalized), $m)) {
            return self::result(
                self::yearToDate((int) $m[1]),
                self::yearToDate((int) $m[2], endOfYear: true),
                DatePrecision::Between,
                $text,
                (int) $m[1],
            );
        }

        // Decades: "1920s"
        if (preg_match('/^(\d{3,4})0s$/u', trim($normalized), $m)) {
            $start = (int) ($m[1].'0');

            return self::result(
                self::yearToDate($start),
                self::yearToDate($start + 9, endOfYear: true),
                DatePrecision::Decade,
                $text,
                $start,
            );
        }

        // Year only: "1932"
        if (preg_match('/^(\d{3,4})$/u', trim($normalized), $m)) {
            $year = (int) $m[1];

            return self::result(
                self::yearToDate($year),
                $precision === DatePrecision::About ? self::yearToDate($year + 5, endOfYear: true) : null,
                $precision ?? DatePrecision::Year,
                $text,
                $year,
            );
        }

        // Month/year: "1932-05" or "May 1932"
        if (preg_match('/^(\d{4})-(\d{1,2})$/u', trim($normalized), $m)) {
            $date = CarbonImmutable::create((int) $m[1], (int) $m[2], 1);

            return self::result(
                $date?->toDateString(),
                null,
                $precision ?? DatePrecision::Month,
                $text,
                (int) $m[1],
            );
        }

        // Anything Carbon can read: full dates, "14 May 1932", "May 1932".
        try {
            $date = CarbonImmutable::parse($normalized);
        } catch (\Throwable) {
            // Unparseable, but the wording is still evidence — keep it verbatim.
            return self::result(null, null, DatePrecision::Unknown, $text, null);
        }

        $resolved = $precision
            ?? ($precisionHint !== null ? DatePrecision::tryFrom($precisionHint) : null)
            ?? (preg_match('/^[a-z]+\s+\d{4}$/u', trim($normalized)) ? DatePrecision::Month : DatePrecision::Exact);

        return self::result($date->toDateString(), null, $resolved, $text, $date->year);
    }

    private static function yearToDate(int $year, bool $endOfYear = false): string
    {
        return sprintf('%04d-%s', $year, $endOfYear ? '12-31' : '01-01');
    }

    /**
     * @return array{date: ?string, end: ?string, precision: string, text: ?string, year: ?int}
     */
    private static function result(?string $date, ?string $end, DatePrecision $precision, ?string $text, ?int $year): array
    {
        return [
            'date' => $date,
            'end' => $end,
            'precision' => $precision->value,
            'text' => $text,
            'year' => $year,
        ];
    }
}
