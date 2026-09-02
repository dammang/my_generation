<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Who added this, who last touched it, who verified it.
 *
 * This is a collaborative archive — knowing who recorded a fact is part of the
 * evidence, so attribution is set from the authenticated user and never from
 * client input.
 */
trait Contributable
{
    protected static function bootContributable(): void
    {
        static::creating(function ($model): void {
            if (Auth::hasUser() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::hasUser()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
