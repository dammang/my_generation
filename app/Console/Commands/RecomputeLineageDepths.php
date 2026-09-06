<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\Tree\LineageDepthService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes generational depth from each designated apical ancestor.
 *
 * Bounded work: one pass per family-branch founder, not per person. This is why
 * "17th Generation" can be displayed without a closure table over the whole
 * database.
 */
class RecomputeLineageDepths extends Command
{
    protected $signature = 'genealogy:recompute-lineage
                            {--root= : Limit to one apical ancestor (person ULID)}
                            {--tribe= : Limit to the branches of one tribe id}';

    protected $description = 'Recompute lineage depths from family branch apical ancestors';

    public function handle(LineageDepthService $service): int
    {
        $roots = $this->roots();

        if ($roots->isEmpty()) {
            $this->warn('No apical ancestors found. Set family_branches.ancestor_person_id first.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($roots as $root) {
            $count = $service->recomputeFor($root);
            $total += $count;

            $this->line("  {$root->display_name}: {$count} descendants");
        }

        $this->info("Recomputed {$total} lineage depths across {$roots->count()} apical ancestors.");

        return self::SUCCESS;
    }

    /** @return Collection<int, Person> */
    private function roots()
    {
        if ($ulid = $this->option('root')) {
            return Person::where('ulid', $ulid)->get();
        }

        $branches = DB::table('family_branches')
            ->whereNotNull('ancestor_person_id')
            ->whereNull('deleted_at')
            ->when($this->option('tribe'), fn ($q, $tribe) => $q->where('tribe_id', $tribe))
            ->get(['id', 'ancestor_person_id']);

        $ids = $branches
            ->map(fn ($branch) => $this->topmostAncestorOf($branch))
            ->unique();

        return Person::whereIn('id', $ids)->get();
    }

    /**
     * The highest ancestor actually recorded, promoting the branch if it has
     * fallen behind.
     *
     * Generations are counted from the branch's founder, and adding somebody
     * above that founder used to leave the count where it was — a person
     * labelled the first generation with their own grandfather above them on
     * the same screen. AddRelative moves it as it goes; this catches the rest:
     * a parent added through the admin panel, an import, or anything written
     * before that existed.
     */
    private function topmostAncestorOf(object $branch): int
    {
        $id = (int) $branch->ancestor_person_id;
        $seen = [$id => true];

        while (true) {
            // family_edges is the denormalised parent/child view the walker
            // uses; going through it keeps this consistent with how every
            // other traversal in the app sees the graph.
            $parent = DB::table('family_edges')
                ->where('child_id', $id)
                ->value('parent_id');

            // No parent, or a cycle somebody managed to record. Either way this
            // is as far up as the data goes.
            if ($parent === null || isset($seen[(int) $parent])) {
                break;
            }

            $id = (int) $parent;
            $seen[$id] = true;
        }

        if ($id !== (int) $branch->ancestor_person_id) {
            DB::table('family_branches')
                ->where('id', $branch->id)
                ->update(['ancestor_person_id' => $id, 'updated_at' => now()]);

            $this->line("  Branch {$branch->id}: founder moved up to person {$id}.");
        }

        return $id;
    }
}
