<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Union;
use App\Services\Graph\GraphSideEffects;
use App\Services\Graph\GraphVersion;
use Illuminate\Support\Facades\DB;

/**
 * Normalises the partner pair and keeps marriage ordering sane.
 *
 * The database CHECK constraint requires partner_1_id < partner_2_id. Enforcing
 * it here as well means callers can pass a couple in whichever order the UI
 * happened to have them, and the unique key still catches the same marriage
 * being entered twice from either spouse's screen.
 */
class UnionObserver
{
    public function __construct(private readonly GraphVersion $graphVersion) {}

    public function saving(Union $union): void
    {
        if ($union->partner_2_id === null) {
            return;
        }

        if ($union->partner_1_id > $union->partner_2_id) {
            [$union->partner_1_id, $union->partner_2_id] = [$union->partner_2_id, $union->partner_1_id];
        }
    }

    public function creating(Union $union): void
    {
        if ($union->order_index !== null && $union->order_index > 1) {
            return;
        }

        // A second marriage is ordered after the first, per the partner who
        // already has unions on record.
        $existing = Union::query()
            ->where(fn ($q) => $q
                ->where('partner_1_id', $union->partner_1_id)
                ->orWhere('partner_2_id', $union->partner_1_id))
            ->max('order_index');

        $union->order_index = ($existing ?? 0) + 1;
    }

    public function created(Union $union): void
    {
        $this->bump($union);
    }

    public function updated(Union $union): void
    {
        $this->bump($union);
    }

    public function deleted(Union $union): void
    {
        $this->bump($union);
    }

    private function bump(Union $union): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->graphVersion->bumpMany(
            DB::table('people')
                ->whereIn('id', array_filter([$union->partner_1_id, $union->partner_2_id]))
                ->pluck('tribe_id')
                ->all()
        );
    }
}
