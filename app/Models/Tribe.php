<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\ScopedEntityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * The top-level heritage group. `graph_version` is bumped on any genealogy write
 * within the tribe and forms part of every tree cache key, so invalidation is O(1).
 */
#[ObservedBy(ScopedEntityObserver::class)]
class Tribe extends Model
{
    use HasFactory, HasPrivacyLevel, HasUlid, RecordsRevisions, SoftDeletesWithUniqueness;

    protected string $privacyColumn = 'default_privacy_level';

    protected $table = 'tribes';

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
        'history',
        'country_code',
        'region',
        'default_privacy_level',
        'status',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'native_name',
        'short_name',
        'description',
        'history',
        'logo_media_id',
        'cover_media_id',
        'country_code',
        'region',
        'primary_place_id',
        'default_privacy_level',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_privacy_level' => PrivacyLevel::class,
            'status' => RecordStatus::class,
        ];
    }

    public function clans(): HasMany
    {
        return $this->hasMany(Clan::class);
    }

    /** Only clans at the top of this tribe's hierarchy. */
    public function rootClans(): HasMany
    {
        return $this->hasMany(Clan::class)->whereNull('parent_clan_id');
    }

    public function familyBranches(): HasMany
    {
        return $this->hasMany(FamilyBranch::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class)->orderBy('generation_number');
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /** The permission scope row standing for this tribe. */
    public function scope(): MorphOne
    {
        return $this->morphOne(Scope::class, 'scopeable');
    }
}
