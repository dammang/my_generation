<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Clan;
use App\Services\Graph\ScopeMaintainer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gives every tribe, clan and family branch its row in the `scopes` spine.
 *
 * One observer for all three: the behaviour is identical, and a scope row that
 * exists for tribes but not for branches would silently break authorization for
 * exactly the records most likely to be family-scoped.
 */
class ScopedEntityObserver
{
    public function __construct(private readonly ScopeMaintainer $scopes) {}

    public function saving(Model $model): void
    {
        if ($model instanceof Clan) {
            $parent = $model->parent_clan_id === null
                ? null
                : Clan::query()->whereKey($model->parent_clan_id)->first(['id', 'depth']);

            $model->depth = $parent === null ? 0 : $parent->depth + 1;
        }
    }

    public function created(Model $model): void
    {
        $this->scopes->sync($model);

        if ($model instanceof Clan) {
            $this->writeClanPath($model);
            $this->bumpClanCount($model, +1);
        }
    }

    public function updated(Model $model): void
    {
        $this->scopes->sync($model);

        if ($model instanceof Clan && $model->wasChanged(['parent_clan_id', 'tribe_id'])) {
            $this->writeClanPath($model);
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Clan) {
            $this->bumpClanCount($model, -1);
        }
    }

    private function writeClanPath(Clan $clan): void
    {
        $parentPath = $clan->parent_clan_id === null
            ? '/'
            : (string) Clan::query()->whereKey($clan->parent_clan_id)->value('path');

        $path = ($parentPath ?: '/').$clan->getKey().'/';

        if ($clan->path === $path) {
            return;
        }

        DB::table('clans')->where('id', $clan->getKey())->update(['path' => $path]);
        $clan->setAttribute('path', $path);
        $clan->syncOriginalAttribute('path');

        foreach (Clan::where('parent_clan_id', $clan->getKey())->get() as $child) {
            $this->writeClanPath($child);
        }
    }

    private function bumpClanCount(Clan $clan, int $delta): void
    {
        DB::table('tribes')->where('id', $clan->tribe_id)->update([
            'clan_count' => DB::raw('GREATEST(CAST(clan_count AS SIGNED) + '.$delta.', 0)'),
        ]);
    }
}
