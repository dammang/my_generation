<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * An open disagreement over a fact. Nothing is deleted: both the 1921 and the
 * 1923 birth year survive as claims.
 */
class Dispute extends Model
{
    use HasUlid;

    protected $table = 'disputes';

    /** @var list<string> */
    protected $fillable = [
        'disputable_type',
        'disputable_id',
        'field',
        'status',
        'opened_by',
        'resolved_by',
        'resolved_at',
        'resolution',
        'resolution_note',
        'accepted_claim_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolution' => DisputeResolution::class,
            'resolved_at' => 'datetime',
        ];
    }
}
