<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChildRelationshipType;
use Illuminate\Database\Eloquent\Model;

/**
 * Groups a child under a couple, for chart layout and birth order.
 *
 * This does NOT assert parentage — the rows in `relationships` do. It is what
 * turns the graph back into the classic chart.
 */
class UnionChild extends Model
{
    protected $table = 'union_children';

    /** @var list<string> */
    protected $fillable = [
        'union_id',
        'person_id',
        'relationship_type',
        'birth_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relationship_type' => ChildRelationshipType::class,
            'birth_order' => 'integer',
        ];
    }
}
