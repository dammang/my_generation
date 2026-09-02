<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Certainty;
use App\Enums\DatePrecision;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use App\Observers\RelationshipObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A directed, canonical, non-partner edge.
 *
 * For parent_child, person_id is ALWAYS the parent and the inverse is never
 * stored. Spouses live in unions; siblings are derived from shared parents.
 */
#[ObservedBy(RelationshipObserver::class)]
class Relationship extends Model
{
    use Contributable, HasFactory, HasUlid, HasVerificationStatus, RecordsRevisions, SoftDeletesWithUniqueness;

    protected $table = 'relationships';

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'person_id',
        'related_person_id',
        'relationship_type',
        'relationship_subtype',
        'is_biological',
        'union_id',
        'start_date',
        'end_date',
        'certainty',
        'verification_status',
        'notes',
    ];

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'related_person_id',
        'relationship_type',
        'relationship_subtype',
        'custom_label',
        'is_biological',
        'union_id',
        'start_date',
        'end_date',
        'date_precision',
        'date_text',
        'place_id',
        'certainty',
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
            'relationship_type' => RelationshipType::class,
            'relationship_subtype' => RelationshipSubtype::class,
            'is_biological' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'date_precision' => DatePrecision::class,
            'certainty' => Certainty::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /** The parent side of a parent_child edge. Always person_id, by construction. */
    public function parentId(): ?int
    {
        return $this->relationship_type === RelationshipType::ParentChild
            ? $this->person_id
            : null;
    }

    public function childId(): ?int
    {
        return $this->relationship_type === RelationshipType::ParentChild
            ? $this->related_person_id
            : null;
    }

    /** The parent, guardian or asserted elder sibling. */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /** The child, ward or asserted younger sibling. */
    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    /** The union this parentage came through, when known. */
    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputable');
    }

    public function scopeParentChild(Builder $query): Builder
    {
        return $query->where('relationship_type', RelationshipType::ParentChild);
    }

    public function scopeBetween(Builder $query, int $personId, int $relatedId): Builder
    {
        return $query->where('person_id', $personId)->where('related_person_id', $relatedId);
    }
}
