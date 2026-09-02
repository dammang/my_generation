<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One position in a dispute, with its own evidence.
 */
class DisputeClaim extends Model
{
    protected $table = 'dispute_claims';

    /** @var list<string> */
    protected $fillable = [
        'dispute_id',
        'claimed_value',
        'rationale',
        'source_id',
        'claimed_by',
        'supporter_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'claimed_value' => 'array',
            'supporter_count' => 'integer',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }
}
