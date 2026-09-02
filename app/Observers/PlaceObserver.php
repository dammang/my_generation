<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Place;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the materialised place path, so "everything within Chin State" is
 * one indexed prefix match rather than a recursive descent.
 */
class PlaceObserver
{
    public function saving(Place $place): void
    {
        $parent = $place->parent_id === null
            ? null
            : Place::query()->whereKey($place->parent_id)->first(['id', 'depth', 'path']);

        $place->depth = $parent === null ? 0 : $parent->depth + 1;
    }

    public function created(Place $place): void
    {
        $this->writePath($place);
    }

    public function updated(Place $place): void
    {
        if ($place->wasChanged('parent_id')) {
            $this->writePath($place);
        }
    }

    private function writePath(Place $place): void
    {
        $parentPath = $place->parent_id === null
            ? '/'
            : (string) Place::query()->whereKey($place->parent_id)->value('path');

        $path = ($parentPath ?: '/').$place->getKey().'/';

        if ($place->path === $path) {
            return;
        }

        DB::table('places')->where('id', $place->getKey())->update(['path' => $path]);
        $place->setAttribute('path', $path);
        $place->syncOriginalAttribute('path');

        $this->repathChildren($place);
    }

    private function repathChildren(Place $place): void
    {
        foreach (Place::where('parent_id', $place->getKey())->get() as $child) {
            $path = $place->path.$child->getKey().'/';

            DB::table('places')->where('id', $child->getKey())
                ->update(['path' => $path, 'depth' => $place->depth + 1]);

            $child->setAttribute('path', $path);
            $child->setAttribute('depth', $place->depth + 1);

            $this->repathChildren($child);
        }
    }
}
