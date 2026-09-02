<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A timed transcript line. Populated in v2.
 */
class OralHistorySegment extends Model
{
    protected $table = 'oral_history_segments';

    /** @var list<string> */
    protected $fillable = [
        'oral_history_id',
        'start_ms',
        'end_ms',
        'speaker',
        'text',
        'translation',
        'confidence',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }
}
