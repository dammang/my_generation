<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DatePrecision;
use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * An immutable genealogical date: what is known, how precisely, and how the
 * source actually wrote it.
 *
 * Constructed from the four-column pattern on any model using HasUncertainDates.
 */
final readonly class UncertainDate implements JsonSerializable
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?CarbonImmutable $end = null,
        public DatePrecision $precision = DatePrecision::Unknown,
        public ?string $text = null,
        public ?int $year = null,
    ) {}

    public function isKnown(): bool
    {
        return $this->date !== null || $this->year !== null;
    }

    /** The source's own wording wins for display, since it is the primary evidence. */
    public function display(): ?string
    {
        return $this->text ?? $this->precision->format($this->date, $this->end);
    }

    /** Earliest year this date could be, given its precision. */
    public function earliestYear(): ?int
    {
        if ($this->year === null) {
            return null;
        }

        return match ($this->precision) {
            DatePrecision::Before => $this->year - 100,
            default => $this->year - $this->precision->tolerance(),
        };
    }

    /** Latest year this date could be, given its precision. */
    public function latestYear(): ?int
    {
        if ($this->year === null) {
            return null;
        }

        return match ($this->precision) {
            DatePrecision::After => $this->year + 100,
            DatePrecision::Between, DatePrecision::Decade => $this->end?->year ?? $this->year + $this->precision->tolerance(),
            default => $this->year + $this->precision->tolerance(),
        };
    }

    /**
     * Whether two dates could describe the same event, allowing for the slack
     * each precision implies. Used by duplicate scoring — an "about 1902" and a
     * "1904" are not a contradiction.
     */
    public function couldMatch(self $other, int $extraTolerance = 0): bool
    {
        if ($this->year === null || $other->year === null) {
            return false;
        }

        return $this->earliestYear() - $extraTolerance <= $other->latestYear()
            && $this->latestYear() + $extraTolerance >= $other->earliestYear();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date?->toDateString(),
            'end' => $this->end?->toDateString(),
            'precision' => $this->precision->value,
            'year' => $this->year,
            'text' => $this->text,
            'display' => $this->display(),
        ];
    }
}
