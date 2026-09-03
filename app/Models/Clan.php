<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToVisibleTribe;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\ScopedEntityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Clan → sub-clan → branch, to arbitrary depth. `depth` records how deep this row
 * sits and `level_label` carries the tribe's own word for that level, because no
 * fixed number of hierarchy levels is assumed.
 */
#[ObservedBy(ScopedEntityObserver::class)]
class Clan extends Model
{
    use BelongsToVisibleTribe;
    use HasFactory, HasUlid, RecordsRevisions, SoftDeletesWithUniqueness;

    protected $table = 'clans';

    /**
     * In-memory defaults mirroring the column defaults.
     *
     * A model created without these reports null for columns the database
     * would have filled, until it is reloaded — which surfaces as a fatal
     * error the moment a resource reads ->value on a null enum.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RecordStatus::Active->value,
        'depth' => 0,
        'people_count' => 0,
        'path' => '',
    ];

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'name',
        'native_name',
        'parent_clan_id',
        'description',
        'history',
        'status',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'parent_clan_id',
        'path',
        'depth',
        'level_label',
        'name',
        'slug',
        'native_name',
        'description',
        'history',
        'logo_media_id',
        'cover_media_id',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'depth' => 'integer',
        ];
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function parentClan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_clan_id');
    }

    public function childClans(): HasMany
    {
        return $this->hasMany(self::class, 'parent_clan_id');
    }

    public function familyBranches(): HasMany
    {
        return $this->hasMany(FamilyBranch::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function scope(): MorphOne
    {
        return $this->morphOne(Scope::class, 'scopeable');
    }

    /**
     * Every clan beneath this one, at any depth, by prefix match on the
     * materialised path — one indexed query instead of a recursive walk.
     */
    public function scopeUnderneath(Builder $query, self $clan): Builder
    {
        return $query->where('path', 'like', $clan->path.'%')->whereKeyNot($clan->getKey());
    }
}
