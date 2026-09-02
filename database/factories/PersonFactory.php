<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use App\Models\Person;
use App\Support\NameCorpus;
use App\Support\UncertainDateParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 *
 * Deliberately generates *imprecise* dates by default. A factory that always
 * produced exact ISO dates would let precision bugs through the entire test
 * suite — real genealogy is mostly "abt. 1902" and "1920s".
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        $given = $gender === Gender::Male
            ? fake()->randomElement(NameCorpus::MALE_GIVEN)
            : fake()->randomElement(NameCorpus::FEMALE_GIVEN);

        $second = fake()->randomElement(NameCorpus::SECOND_ELEMENT);
        $displayName = "{$given} {$second}";

        return [
            'first_name' => $given,
            'last_name' => $second,
            'display_name' => $displayName,
            'sort_name' => mb_strtolower("{$second} {$given}"),
            'nickname' => fake()->boolean(15) ? $given : null,
            'gender' => $gender,
            'is_living' => true,
            'privacy_level' => PrivacyLevel::Family,
            'verification_status' => VerificationStatus::Unverified,
            ...self::birthColumns(self::imperfectYearExpression(fake()->numberBetween(1900, 1995))),
        ];
    }

    /** How a source might actually have written a year. */
    private static function imperfectYearExpression(int $year): string
    {
        return fake()->randomElement([
            (string) $year,
            (string) $year,
            'abt. '.$year,
            ($year - ($year % 10)).'s',
            $year.'-'.fake()->numberBetween(1, 12),
            // Built by hand rather than with fake()->date(): Faker's max-date
            // argument is unreliable before 1970, where Unix timestamps go
            // negative, and most genealogy dates are before 1970.
            sprintf('%04d-%02d-%02d', $year, fake()->numberBetween(1, 12), fake()->numberBetween(1, 28)),
        ]);
    }

    /** @return array<string, mixed> */
    private static function birthColumns(string $expression): array
    {
        $parsed = UncertainDateParser::parse($expression);

        return [
            'birth_date' => $parsed['date'],
            'birth_date_end' => $parsed['end'],
            'birth_date_precision' => $parsed['precision'],
            'birth_date_text' => $parsed['text'],
            'birth_year' => $parsed['year'],
        ];
    }

    /**
     * Born in a given year, at a precision drawn from what sources actually
     * offer — so the resulting birth_year may legitimately differ from $year
     * (a "1920s" reading of 1926 stores 1920). Use bornExactly() when a test
     * needs a determinate year.
     */
    public function bornAround(int $year): static
    {
        return $this->state(fn () => self::birthColumns(self::imperfectYearExpression($year)));
    }

    /** Born in exactly this year, at year precision. For deterministic tests. */
    public function bornExactly(int $year): static
    {
        return $this->state(fn () => self::birthColumns((string) $year));
    }

    public function deceased(?int $deathYear = null): static
    {
        return $this->state(function (array $attributes) use ($deathYear) {
            $birth = $attributes['birth_year'] ?? fake()->numberBetween(1850, 1940);
            $year = $deathYear ?? min($birth + fake()->numberBetween(28, 92), (int) date('Y'));
            $parsed = UncertainDateParser::parse(fake()->boolean(60) ? (string) $year : 'abt. '.$year);

            return [
                'is_living' => false,
                'death_date' => $parsed['date'],
                'death_date_end' => $parsed['end'],
                'death_date_precision' => $parsed['precision'],
                'death_date_text' => $parsed['text'],
                'death_year' => $parsed['year'],
            ];
        });
    }

    public function living(): static
    {
        return $this->state(fn () => ['is_living' => true, 'death_date' => null, 'death_year' => null]);
    }

    /** A person with no dates at all — the fail-closed privacy case. */
    public function undated(): static
    {
        return $this->state(fn () => [
            'birth_date' => null, 'birth_year' => null,
            'death_date' => null, 'death_year' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verification_status' => VerificationStatus::Verified]);
    }

    public function public(): static
    {
        return $this->state(fn () => ['privacy_level' => PrivacyLevel::Public]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['privacy_level' => PrivacyLevel::Private]);
    }

    public function male(): static
    {
        return $this->state(fn () => [
            'gender' => Gender::Male,
            'first_name' => fake()->randomElement(NameCorpus::MALE_GIVEN),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'gender' => Gender::Female,
            'first_name' => fake()->randomElement(NameCorpus::FEMALE_GIVEN),
        ]);
    }

    public function named(string $displayName): static
    {
        $parts = explode(' ', $displayName);

        return $this->state(fn () => [
            'display_name' => $displayName,
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? null,
            'sort_name' => mb_strtolower(($parts[1] ?? '').' '.$parts[0]),
        ]);
    }
}
