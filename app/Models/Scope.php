<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The permission spine: one row per tribe, clan and family branch.
 * `path` is a materialised list of scope ids, so authority checks are a prefix
 * comparison instead of a recursive query.
 */
class Scope extends Model
{
    protected $table = 'scopes';

    /** @var list<string> */
    protected $fillable = [
        'scopeable_type',
        'scopeable_id',
        'parent_scope_id',
        'path',
        'depth',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
        ];
    }

    /** Scopes whose path this scope's path starts with — i.e. its ancestors. */
    public function isDescendantOfPath(string $path): bool
    {
        return str_starts_with($this->path, $path);
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_scope_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_scope_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** Every scope beneath this one, by prefix match — no recursion. */
    public function scopeUnderneath(Builder $query, self $scope): Builder
    {
        return $query->where('path', 'like', $scope->path.'%');
    }
}
