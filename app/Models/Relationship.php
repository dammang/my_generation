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
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A directed, canonical, non-partner edge.
 *
 * For parent_child, person_id is ALWAYS the parent and the inverse is never
 * stored. Spouses live in unions; siblings are derived from shared parents.
 */
class Relationship extends Model
{
    use Contributable, HasFactory, HasUlid, HasVerificationStatus, SoftDeletesWithUniqueness;

    protected $table = 'relationships';

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
}
