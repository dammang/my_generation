<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DatePrecision;
use App\Support\UncertainDate;
use App\Support\UncertainDateParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Genealogy input is rarely an ISO date. These cases are the ones that actually
 * appear in church registers, family bibles and oral testimony.
 */
class UncertainDateParserTest extends TestCase
{
    /** @return array<string, array{0:string, 1:string, 2:?string, 3:?int}> */
    public static function expressions(): array
    {
        return [
            'exact date' => ['1932-05-14', DatePrecision::Exact->value,   '1932-05-14', 1932],
            'written date' => ['14 May 1932', DatePrecision::Exact->value,  '1932-05-14', 1932],
            'month and year' => ['1932-05',    DatePrecision::Month->value,   '1932-05-01', 1932],
            'year only' => ['1902',       DatePrecision::Year->value,    '1902-01-01', 1902],
            'approximate' => ['abt. 1902',  DatePrecision::About->value,   '1902-01-01', 1902],
            'circa' => ['circa 1902', DatePrecision::About->value,   '1902-01-01', 1902],
            'before' => ['before 1940', DatePrecision::Before->value, '1940-01-01', 1940],
            'after' => ['aft. 1940',  DatePrecision::After->value,   '1940-01-01', 1940],
            'decade' => ['1920s',      DatePrecision::Decade->value,  '1920-01-01', 1920],
            'range' => ['1920-1925',  DatePrecision::Between->value, '1920-01-01', 1920],
            'between' => ['between 1920 and 1925', DatePrecision::Between->value, '1920-01-01', 1920],
            'unparseable' => ['before the war', DatePrecision::Unknown->value, null, null],
            'empty' => ['',           DatePrecision::Unknown->value, null, null],
        ];
    }

    #[DataProvider('expressions')]
    public function test_it_parses_the_way_sources_are_actually_written(
        string $input,
        string $precision,
        ?string $date,
        ?int $year,
    ): void {
        $parsed = UncertainDateParser::parse($input);

        $this->assertSame($precision, $parsed['precision'], "precision for '{$input}'");
        $this->assertSame($date, $parsed['date'], "date for '{$input}'");
        $this->assertSame($year, $parsed['year'], "year for '{$input}'");
    }

    public function test_it_preserves_the_sources_own_wording(): void
    {
        // The verbatim wording is primary evidence and must survive parsing.
        $this->assertSame('abt. 1902', UncertainDateParser::parse('abt. 1902')['text']);
        $this->assertSame('before the war', UncertainDateParser::parse('before the war')['text']);
    }

    public function test_a_range_gets_an_upper_bound(): void
    {
        $parsed = UncertainDateParser::parse('1920-1925');

        $this->assertSame('1925-12-31', $parsed['end']);
    }

    public function test_a_decade_spans_ten_years(): void
    {
        $parsed = UncertainDateParser::parse('1920s');

        $this->assertSame('1920-01-01', $parsed['date']);
        $this->assertSame('1929-12-31', $parsed['end']);
    }

    public function test_uncertain_dates_can_match_across_their_tolerance(): void
    {
        $about1902 = new UncertainDate(
            CarbonImmutable::parse('1902-01-01'), null, DatePrecision::About, 'abt. 1902', 1902
        );
        $exact1904 = new UncertainDate(
            CarbonImmutable::parse('1904-03-02'), null, DatePrecision::Exact, null, 1904
        );
        $exact1935 = new UncertainDate(
            CarbonImmutable::parse('1935-03-02'), null, DatePrecision::Exact, null, 1935
        );

        // "about 1902" and "1904" are not a contradiction; 1935 is.
        $this->assertTrue($about1902->couldMatch($exact1904));
        $this->assertFalse($about1902->couldMatch($exact1935));
    }

    public function test_display_prefers_the_sources_wording(): void
    {
        $date = new UncertainDate(
            CarbonImmutable::parse('1902-01-01'), null, DatePrecision::About, 'abt. 1902', 1902
        );

        $this->assertSame('abt. 1902', $date->display());
    }
}
