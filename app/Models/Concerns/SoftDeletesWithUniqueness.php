<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soft deletes that do not silently break unique indexes.
 *
 * MySQL treats NULLs as distinct, so a unique key containing `deleted_at` still
 * permits duplicate live rows — the classic soft-delete uniqueness bug. Every
 * unique key in this schema therefore ends in `deleted_token`, which is 0 while
 * the row is live and the row's own id once it is deleted.
 *
 * The token is written after the soft delete rather than during it, because
 * Eloquent's runSoftDelete() only persists deleted_at and updated_at.
 */
trait SoftDeletesWithUniqueness
{
    use SoftDeletes;

    protected static function bootSoftDeletesWithUniqueness(): void
    {
        static::registerModelEvent('trashed', function ($model): void {
            $token = $model->getKey();

            $model->newQueryWithoutScopes()
                ->whereKey($token)
                ->update(['deleted_token' => $token]);

            $model->setAttribute('deleted_token', $token);
            $model->syncOriginalAttribute('deleted_token');
        });

        // restore() calls save(), so this one persists on its own.
        static::restoring(function ($model): void {
            $model->setAttribute('deleted_token', 0);
        });
    }

    public function isLive(): bool
    {
        return (int) $this->getAttribute('deleted_token') === 0;
    }
}
