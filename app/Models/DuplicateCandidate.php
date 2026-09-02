<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * A scored possible duplicate pair. `signals` records each feature's contribution
 * so a reviewer can see WHY a pair scored as it did. Nothing merges automatically,
 * at any score.
 */
class DuplicateCandidate extends Model
{
    use HasUlid;

    protected $table = 'duplicate_candidates';

    /** @var list<string> */
    protected $fillable = [
        'person_a_id',
        'person_b_id',
        'score',
        'signals',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'signals' => 'array',
            'status' => DuplicateStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
