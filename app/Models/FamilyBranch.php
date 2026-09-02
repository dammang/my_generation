<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\ScopedEntityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A named family line. `ancestor_person_id` is the apical ancestor this branch
 * counts generations from — the root used by lineage_depths.
 */
#[ObservedBy(ScopedEntityObserver::class)]
class FamilyBranch extends Model
{
    use HasFactory, HasUlid, RecordsRevisions, SoftDeletesWithUniqueness;

    protected $table = 'family_branches';

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
        'description',
        'ancestor_person_id',
        'origin_place_id',
        'status',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'clan_id',
        'ancestor_person_id',
        'name',
        'slug',
        'native_name',
        'description',
        'origin_place_id',
        'current_place_id',
        'current_region',
        'cover_media_id',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /** The apical ancestor this branch counts its generations from. */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'ancestor_person_id');
    }

    public function originPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'origin_place_id');
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function scope(): MorphOne
    {
        return $this->morphOne(Scope::class, 'scopeable');
    }
}
