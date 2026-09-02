<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchKeyType;
use Illuminate\Database\Eloquent\Model;

/**
 * A blocking key. Two people are compared for duplication only if they share one,
 * which turns O(n²) into O(n·k). Composite primary key, no timestamps: this table
 * is written in bulk and read by index only.
 */
class PersonMatchKey extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'person_match_keys';

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'key_type',
        'key_value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'key_type' => MatchKeyType::class,
        ];
    }
}
