<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user rollup counters. Incremented on write, reconciled nightly against
 * revisions. Powers the "My Contributions" screen.
 */
class ContributionStat extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $table = 'contribution_stats';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'people_added',
        'relationships_added',
        'unions_added',
        'events_added',
        'stories_added',
        'sources_added',
        'media_added',
        'changes_approved',
        'changes_rejected',
        'verifications_made',
        'last_contributed_at',
        'recalculated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_contributed_at' => 'datetime',
            'recalculated_at' => 'datetime',
        ];
    }
}
