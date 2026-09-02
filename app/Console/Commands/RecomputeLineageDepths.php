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

        $ids = DB::table('family_branches')
            ->whereNotNull('ancestor_person_id')
            ->whereNull('deleted_at')
            ->when($this->option('tribe'), fn ($q, $tribe) => $q->where('tribe_id', $tribe))
            ->pluck('ancestor_person_id')
            ->unique();

        return Person::whereIn('id', $ids)->get();
    }
}
