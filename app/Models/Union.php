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
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A marriage or partnership.
 *
 * A partnership is an entity, not an edge — it has its own date, place, type and
 * end state, and its own children. partner_1_id is always the lower internal id,
 * which is what makes the unique key actually prevent duplicate marriages.
 */
class Union extends Model
{
    use Contributable, HasFactory, HasUlid, HasUncertainDates, HasVerificationStatus, SoftDeletesWithUniqueness;

    /** @var array<int, string> */
    protected array $uncertainDates = ['marriage'];

    protected $table = 'unions';

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
}
