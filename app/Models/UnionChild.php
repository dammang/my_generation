<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChildRelationshipType;
use App\Observers\UnionChildObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Groups a child under a couple, for chart layout and birth order.
 *
 * This does NOT assert parentage — the rows in `relationships` do. It is what
 * turns the graph back into the classic chart.
 */
#[ObservedBy(UnionChildObserver::class)]
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

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
