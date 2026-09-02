<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

trait HasLabel
{
    /** Human label, translatable via the enums.* lang file. */
    public function label(): string
    {
        $key = 'enums.'.class_basename(self::class).'.'.$this->value;

        return __($key) === $key
            ? str(str_replace('_', ' ', $this->value))->title()->toString()
            : __($key);
    }

    /** @return array<string, string> value => label, for form selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<int, string> raw values, for validation rules. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
