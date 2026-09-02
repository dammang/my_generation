<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\PlaceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the reusable gazetteer: country → state → district → township → village.
 * Depth is data, not schema — `type` is a string because jurisdictions differ.
 */
#[ObservedBy(PlaceObserver::class)]
class Place extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'places';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'path',
        'depth',
        'name',
        'native_name',
        'type',
        'country_code',
        'latitude',
        'longitude',
        'historical_names',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'historical_names' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'depth' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function bornHere(): HasMany
    {
        return $this->hasMany(Person::class, 'birth_place_id');
    }

    /** Everything beneath this place, at any depth, by materialised path. */
    public function scopeWithin(Builder $query, self $place): Builder
    {
        return $query->where('path', 'like', $place->path.'%')->whereKeyNot($place->getKey());
    }

    /** "Khuasak, Tedim, Chin State, Myanmar" — built from the path, no recursion. */
    public function fullName(): string
    {
        $ids = array_filter(explode('/', $this->path));

        return self::query()
            ->whereIn('id', $ids)
            ->orderByDesc('depth')
            ->pluck('name')
            ->implode(', ');
    }
}
