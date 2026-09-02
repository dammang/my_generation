<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EdgeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived traversal adjacency — a cache, not truth.
 *
 * Every tree CTE reads only this table, because both of its indexes are covering.
 * Written in bulk by observers and rebuildable at any time with
 * `php artisan genealogy:rebuild-edges`. No surrogate key and no timestamps: at
 * millions of rows, every byte here is a byte of buffer pool.
 */
class FamilyEdge extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'family_edges';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'child_id',
        'edge_kind',
        'tribe_id',
        'confidence',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'edge_kind' => EdgeKind::class,
            'confidence' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'parent_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'child_id');
    }
}
