<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Depth from a designated apical ancestor — how "17th Generation" is displayed
 * without a closure table over all people.
 *
 * min/max differ under pedigree collapse (cousins marrying), so the UI can show a
 * range rather than inventing one answer.
 */
class LineageDepth extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'lineage_depths';

    /** @var list<string> */
    protected $fillable = [
        'root_person_id',
        'person_id',
        'depth',
        'min_depth',
        'max_depth',
        'path_count',
        'computed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'min_depth' => 'integer',
            'max_depth' => 'integer',
            'path_count' => 'integer',
            'computed_at' => 'datetime',
        ];
    }
}
