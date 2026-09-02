<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\ContributionStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-user contribution counters.
 *
 * Incremented on write and reconciled nightly against the revision ledger, so a
 * missed increment self-corrects rather than permanently understating somebody's
 * work. Attribution is part of the evidence in a collaborative archive: knowing
 * who recorded a fact matters as much as the fact.
 */
class ContributionCounter
{
    public function increment(User $user, string $column, int $by = 1): void
    {
        DB::table('contribution_stats')->upsert(
            [[
                'user_id' => $user->getKey(),
                $column => $by,
                'last_contributed_at' => now(),
            ]],
            ['user_id'],
            [
                $column => DB::raw("contribution_stats.{$column} + ".$by),
                'last_contributed_at' => now(),
            ],
        );
    }

    public function forUser(User $user): ContributionStat
    {
        return ContributionStat::firstOrCreate(['user_id' => $user->getKey()]);
    }
}
