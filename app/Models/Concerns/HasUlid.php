<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Public identifier. Internal bigint ids never appear in an API response:
 * they leak row counts and creation order, and route binding on them would
 * make ids guessable. ULIDs are monotonic, so they still index well.
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
