<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Enums\RelationshipType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasUncertainDates;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\PersonObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The person node.
 *
 * A user account is NOT a person: a deceased grandfather exists here with no
 * account, and a user is linked only through an approved profile claim.
 */
#[ObservedBy(PersonObserver::class)]
class Person extends Model
{
    use Contributable, HasFactory, HasPrivacyLevel, HasUlid, HasUncertainDates, HasVerificationStatus, RecordsRevisions, SoftDeletesWithUniqueness;

    /** @var array<int, string> */
    protected array $uncertainDates = ['birth', 'death'];

    protected $table = 'people';

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'first_name',
        'middle_name',
        'last_name',
        'native_name',
        'nickname',
        'display_name',
        'gender',
        'birth_date',
        'birth_date_precision',
        'birth_date_text',
        'birth_place_id',
        'death_date',
        'death_date_precision',
        'death_date_text',
        'death_place_id',
        'burial_place_id',
        'is_living',
        'biography',
        'tribe_id',
        'clan_id',
        'family_branch_id',
        'generation_id',
        'privacy_level',
        'verification_status',
        'merged_into_person_id',
    ];

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'native_name',
        'nickname',
        'display_name',
        'sort_name',
        'gender',
        'birth_date',
        'birth_date_end',
        'birth_date_precision',
        'birth_date_text',
        'birth_year',
        'birth_place_id',
        'death_date',
        'death_date_end',
        'death_date_precision',
        'death_date_text',
        'death_year',
        'death_place_id',
        'burial_place_id',
        'is_living',
        'living_reviewed_at',
        'biography',
        'profile_media_id',
        'cover_media_id',
        'tribe_id',
        'clan_id',
        'family_branch_id',
        'generation_id',
        'privacy_level',
        'verification_status',
        'has_open_dispute',
        'merged_into_person_id',
        'external_ref',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'birth_date_end' => 'date',
            'death_date' => 'date',
            'death_date_end' => 'date',
            'birth_date_precision' => DatePrecision::class,
            'death_date_precision' => DatePrecision::class,
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_living' => 'boolean',
            'has_open_dispute' => 'boolean',
            'privacy_level' => PrivacyLevel::class,
            'verification_status' => VerificationStatus::class,
            'living_reviewed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Deceased only when the record proves it. A person with no dates at all is
     * treated as living, and gets the strictest privacy handling — fail closed.
     */
    public function isDeceased(): bool
    {
        if ($this->death_date !== null || $this->death_year !== null) {
            return true;
        }

        $maxAge = (int) config('genealogy.living.max_age');

        return $this->birth_year !== null
            && $this->birth_year < (int) date('Y') - $maxAge;
    }

    public function isMinor(): bool
    {
        if ($this->isDeceased() || $this->birth_year === null) {
            return false;
        }

        return $this->birth_year > (int) date('Y') - (int) config('genealogy.living.minor_age');
    }

    /** A merged-away record: kept so old ULIDs and share links still resolve. */
    public function isTombstone(): bool
    {
        return $this->merged_into_person_id !== null;
    }

    /** Lifespan as shown on a tree card, e.g. "1920–1998" or "b. 1975". */
    public function lifespan(): ?string
    {
        $birth = $this->birth_year;
        $death = $this->death_year;

        return match (true) {
            $birth !== null && $death !== null => "{$birth}–{$death}",
            $birth !== null => "b. {$birth}",
            $death !== null => "d. {$death}",
            default => null,
        };
    }

    // ── Placement ────────────────────────────────────────────────────────

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function familyBranch(): BelongsTo
    {
        return $this->belongsTo(FamilyBranch::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /** Secondary tribe/clan affiliations, for mixed-marriage lineages. */
    public function affiliations(): HasMany
    {
        return $this->hasMany(PersonAffiliation::class);
    }

    public function birthPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'birth_place_id');
    }

    public function deathPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'death_place_id');
    }

    public function burialPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'burial_place_id');
    }

    public function profileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'profile_media_id');
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    // ── Identity ─────────────────────────────────────────────────────────

    /** Alternate, native, historical and translated spellings. */
    public function names(): HasMany
    {
        return $this->hasMany(PersonName::class);
    }

    public function matchKeys(): HasMany
    {
        return $this->hasMany(PersonMatchKey::class);
    }

    /** The account verified as this person, if any. Usually none. */
    public function claimedByUser(): HasOne
    {
        return $this->hasOne(User::class, 'person_id');
    }

    public function profileClaims(): HasMany
    {
        return $this->hasMany(ProfileClaim::class);
    }

    /** Set when this record lost a merge; old ULIDs redirect here. */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_person_id');
    }

    // ── Genealogy edges ──────────────────────────────────────────────────

    /**
     * Relationship rows in which this person is the CHILD.
     * Direction is canonical: person_id is always the parent.
     */
    public function parentEdges(): HasMany
    {
        return $this->hasMany(Relationship::class, 'related_person_id')
            ->where('relationship_type', RelationshipType::ParentChild);
    }

    /** Relationship rows in which this person is the PARENT. */
    public function childEdges(): HasMany
    {
        return $this->hasMany(Relationship::class, 'person_id')
            ->where('relationship_type', RelationshipType::ParentChild);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'relationships', 'related_person_id', 'person_id')
            ->wherePivot('relationship_type', RelationshipType::ParentChild->value)
            ->wherePivotNull('deleted_at')
            ->withPivot(['relationship_subtype', 'is_biological', 'certainty', 'verification_status']);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'relationships', 'person_id', 'related_person_id')
            ->wherePivot('relationship_type', RelationshipType::ParentChild->value)
            ->wherePivotNull('deleted_at')
            ->withPivot(['relationship_subtype', 'is_biological', 'certainty', 'verification_status']);
    }

    /**
     * Unions are split across two columns because a partnership is an entity
     * with a normalised pair (partner_1_id < partner_2_id), not a directed
     * edge. Eloquent cannot express "either column" as one relation, so both
     * are declared for eager loading and allUnions() merges them.
     */
    public function unionsAsPartner1(): HasMany
    {
        return $this->hasMany(Union::class, 'partner_1_id');
    }

    public function unionsAsPartner2(): HasMany
    {
        return $this->hasMany(Union::class, 'partner_2_id');
    }

    /** Rows placing this person as a child within a union. */
    public function unionMemberships(): HasMany
    {
        return $this->hasMany(UnionChild::class);
    }

    // ── Chronicle, content and evidence ──────────────────────────────────

    public function events(): HasMany
    {
        return $this->hasMany(PersonEvent::class)->orderBy('event_year')->orderBy('event_date');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_people')->withPivot('role');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputable');
    }

    // ── Derived family, without a traversal ──────────────────────────────

    /**
     * Every union this person is a partner in, from either column.
     * Ordered so a first marriage precedes a second.
     */
    public function allUnions(): Collection
    {
        return Union::query()
            ->where('partner_1_id', $this->getKey())
            ->orWhere('partner_2_id', $this->getKey())
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Spouses are derived from unions rather than stored as relationship rows.
     * Storing them would duplicate every union's attributes onto an edge.
     */
    public function spouses(): Collection
    {
        $ids = $this->allUnions()
            ->map(fn (Union $union) => $union->partnerOf($this->getKey()))
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? new Collection
            : self::query()->whereIn('id', $ids)->get();
    }

    /**
     * Siblings are people sharing at least one parent — derived, never stored,
     * because storing sibship is O(n²) per family and drifts on every edit.
     * Full siblings share two or more.
     */
    public function siblings(bool $fullOnly = false): Collection
    {
        $parentIds = $this->parentEdges()->pluck('person_id');

        if ($parentIds->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->whereKeyNot($this->getKey())
            ->whereHas('parentEdges', fn (Builder $q) => $q->whereIn('person_id', $parentIds),
                operator: '>=',
                count: $fullOnly ? 2 : 1)
            ->get();
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeLiving(Builder $query): Builder
    {
        return $query->where('is_living', true);
    }

    public function scopeDeceased(Builder $query): Builder
    {
        return $query->where('is_living', false);
    }

    /** Excludes merge tombstones, which exist only so old links still resolve. */
    public function scopeNotMerged(Builder $query): Builder
    {
        return $query->whereNull('merged_into_person_id');
    }

    public function scopeInTribe(Builder $query, int $tribeId): Builder
    {
        return $query->where('tribe_id', $tribeId);
    }

    public function scopeInClan(Builder $query, int $clanId): Builder
    {
        return $query->where('clan_id', $clanId);
    }

    public function scopeInBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('family_branch_id', $branchId);
    }
}
