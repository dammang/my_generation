<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasUncertainDates;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\UnionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A marriage or partnership.
 *
 * A partnership is an entity, not an edge — it has its own date, place, type and
 * end state, and its own children. partner_1_id is always the lower internal id,
 * which is what makes the unique key actually prevent duplicate marriages.
 */
#[ObservedBy(UnionObserver::class)]
class Union extends Model
{
    use Contributable, HasFactory, HasUlid, HasUncertainDates, HasVerificationStatus, RecordsRevisions, SoftDeletesWithUniqueness;

    /** @var array<int, string> */
    protected array $uncertainDates = ['marriage'];

    protected $table = 'unions';

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
        'union_type' => UnionType::Marriage->value,
        'status' => UnionStatus::Unknown->value,
        'verification_status' => VerificationStatus::Unverified->value,
        'order_index' => 1,
        'children_count' => 0,
    ];

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'partner_1_id',
        'partner_2_id',
        'union_type',
        'status',
        'marriage_date',
        'marriage_date_precision',
        'marriage_place_id',
        'separation_date',
        'divorce_date',
        'verification_status',
        'notes',
    ];

    /** @var list<string> */
    protected $fillable = [
        'partner_1_id',
        'partner_2_id',
        'union_type',
        'status',
        'marriage_date',
        'marriage_date_end',
        'marriage_date_precision',
        'marriage_date_text',
        'marriage_year',
        'marriage_place_id',
        'separation_date',
        'divorce_date',
        'order_index',
        'children_count',
        'verification_status',
        'notes',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'union_type' => UnionType::class,
            'status' => UnionStatus::class,
            'marriage_date' => 'date',
            'marriage_date_end' => 'date',
            'marriage_date_precision' => DatePrecision::class,
            'marriage_year' => 'integer',
            'separation_date' => 'date',
            'divorce_date' => 'date',
            'order_index' => 'integer',
            'children_count' => 'integer',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /** Single-parent families are real: partner_2_id is legitimately null. */
    public function isSingleParent(): bool
    {
        return $this->partner_2_id === null;
    }

    /** The other partner, given one of them. */
    public function partnerOf(int $personId): ?int
    {
        return match ($personId) {
            $this->partner_1_id => $this->partner_2_id,
            $this->partner_2_id => $this->partner_1_id,
            default => null,
        };
    }

    public function partner1(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'partner_1_id');
    }

    public function partner2(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'partner_2_id');
    }

    public function marriagePlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'marriage_place_id');
    }

    /** The grouping rows, ordered for chart layout. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(UnionChild::class)->orderBy('birth_order');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'union_children')
            ->withPivot(['relationship_type', 'birth_order'])
            ->orderByPivot('birth_order');
    }

    /** The parentage rows this union produced. */
    public function parentEdges(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function scopeInvolving(Builder $query, int $personId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('partner_1_id', $personId)
            ->orWhere('partner_2_id', $personId));
    }
}
