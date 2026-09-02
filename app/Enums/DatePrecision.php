<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;
use Carbon\CarbonImmutable;

/**
 * How much of a genealogical date is actually known.
 *
 * Genealogy dates are frequently partial or approximate, so every date fact is
 * stored as four columns: x_date (normalised to the earliest day of the known
 * period), x_date_end (upper bound), x_date_precision, and x_date_text (the
 * source's verbatim wording). This enum drives both display and range queries.
 */
enum DatePrecision: string
{
    use HasLabel;

    case Exact = 'exact';
    case Month = 'month';
    case Year = 'year';
    case Decade = 'decade';
    case About = 'about';
    case Before = 'before';
    case After = 'after';
    case Between = 'between';
    case Unknown = 'unknown';

    /** Whether this precision needs an upper bound in x_date_end. */
    public function needsEndDate(): bool
    {
        return in_array($this, [self::Decade, self::About, self::Between], true);
    }

    /** Whether a day component is meaningful at this precision. */
    public function hasDay(): bool
    {
        return $this === self::Exact;
    }

    /** Years of slack to allow either side when comparing two dates. */
    public function tolerance(): int
    {
        return match ($this) {
            self::Exact, self::Month => 0,
            self::Year, self::Before, self::After => 1,
            self::About, self::Between => 5,
            self::Decade => 10,
            self::Unknown => 0,
        };
    }

    /** Render for display, e.g. "abt. 1902", "before 1940", "1920s". */
    public function format(?CarbonImmutable $date, ?CarbonImmutable $end = null): ?string
    {
        if ($date === null) {
            return null;
        }

        return match ($this) {
            self::Exact => $date->format('j M Y'),
            self::Month => $date->format('M Y'),
            self::Year => $date->format('Y'),
            self::Decade => $date->format('Y').'s',
            self::About => 'abt. '.$date->format('Y'),
            self::Before => 'before '.$date->format('Y'),
            self::After => 'after '.$date->format('Y'),
            self::Between => $date->format('Y').'–'.($end?->format('Y') ?? '?'),
            self::Unknown => null,
        };
    }
}
