<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\DatePrecision;
use App\Support\UncertainDate;
use App\Support\UncertainDateParser;
use Carbon\CarbonImmutable;

/**
 * The four-column uncertain-date pattern, as model behaviour.
 *
 * Declare the prefixes on the model:
 *
 *     protected array $uncertainDates = ['birth', 'death'];
 *
 * Reading:  $person->dateFact('birth')->display()   // "abt. 1902"
 * Writing:  $person->setUncertainDate('birth', 'abt. 1902')
 *
 * The derived _year column is kept in sync on every save, because it is what
 * every range query and every duplicate-matching comparison actually uses.
 */
trait HasUncertainDates
{
    protected static function bootHasUncertainDates(): void
    {
        static::saving(function ($model): void {
            foreach ($model->uncertainDatePrefixes() as $prefix) {
                $date = $model->getAttribute("{$prefix}_date");

                $model->setAttribute(
                    "{$prefix}_year",
                    $date === null ? null : CarbonImmutable::parse($date)->year
                );
            }
        });
    }

    /** @return array<int, string> */
    public function uncertainDatePrefixes(): array
    {
        return $this->uncertainDates ?? [];
    }

    public function dateFact(string $prefix): UncertainDate
    {
        $date = $this->getAttribute("{$prefix}_date");
        $end = $this->getAttribute("{$prefix}_date_end");
        $precision = $this->getAttribute("{$prefix}_date_precision");

        return new UncertainDate(
            date: $date === null ? null : CarbonImmutable::parse($date),
            end: $end === null ? null : CarbonImmutable::parse($end),
            precision: $precision instanceof DatePrecision
                ? $precision
                : DatePrecision::tryFrom((string) $precision) ?? DatePrecision::Unknown,
            text: $this->getAttribute("{$prefix}_date_text"),
            year: $this->getAttribute("{$prefix}_year"),
        );
    }

    /**
     * Accepts anything a contributor might type. The original wording is kept
     * verbatim in _date_text; pass $keepText = false when the input is machine
     * generated (an import, a seeder) and there is no human wording to preserve.
     */
    public function setUncertainDate(string $prefix, ?string $input, bool $keepText = true): static
    {
        $parsed = UncertainDateParser::parse($input);

        $this->setAttribute("{$prefix}_date", $parsed['date']);
        $this->setAttribute("{$prefix}_date_end", $parsed['end']);
        $this->setAttribute("{$prefix}_date_precision", $parsed['precision']);
        $this->setAttribute("{$prefix}_date_text", $keepText ? $parsed['text'] : null);
        $this->setAttribute("{$prefix}_year", $parsed['year']);

        return $this;
    }
}
