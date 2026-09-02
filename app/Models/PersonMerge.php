<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reversible merge. `moved_records` logs every foreign key repointed, so
 * reversal replays it backwards; `loser_snapshot` restores the record itself.
 */
class PersonMerge extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'person_merges';

    /** @var list<string> */
    protected $fillable = [
        'winner_person_id',
        'loser_person_id',
        'field_choices',
        'moved_records',
        'loser_snapshot',
        'merged_by',
        'merged_at',
        'reverted_by',
        'reverted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'field_choices' => 'array',
            'moved_records' => 'array',
            'loser_snapshot' => 'array',
            'merged_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'winner_person_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'loser_person_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
