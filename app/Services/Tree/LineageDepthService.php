<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * "17th Generation", without a closure table over the whole database.
 *
 * Depths are stored only for designated apical ancestors — family branch
 * founders — which is a few hundred roots rather than millions. A full closure
 * would be hundreds of millions of rows with catastrophic write amplification;
 * this is bounded at roots × descendants-of-that-root.
 *
 * min and max differ under pedigree collapse, when cousins marry: a person can
 * be 14 generations from the founder down one line and 16 down another. Both
 * are recorded so the UI can show a range instead of inventing one answer.
 */
class LineageDepthService
{
    /** Deep enough for any real pedigree, bounded so a corrupt graph cannot hang. */
    private const MAX_DESCENT = 64;

    public function __construct(private readonly GraphWalker $walker) {}

    public function recomputeFor(Person $root): int
    {
        $minDepths = $this->walker->descend($root->getKey(), self::MAX_DESCENT);
        $maxDepths = $this->walker->longestDescent($root->getKey(), $minDepths);

        DB::table('lineage_depths')->where('root_person_id', $root->getKey())->delete();

        $now = now();
        $rootId = $root->getKey();
        $buffer = [];
        $written = 0;

        // Streamed rather than accumulated: a founder can reach most of a
        // tribe, and holding a hundred thousand row arrays before the first
        // insert is how this ran out of memory.
        foreach ($minDepths as $personId => $min) {
            $max = $maxDepths[$personId] ?? $min;

            $buffer[] = [
                'root_person_id' => $rootId,
                'person_id' => $personId,
                // The displayed generation is the shortest line to the founder.
                'depth' => $min,
                'min_depth' => $min,
                'max_depth' => $max,
                // Distinct descent paths are exponential in a DAG and not worth
                // counting; min differing from max already says that more than
                // one line of descent exists.
                'path_count' => $min === $max ? 1 : 2,
                'computed_at' => $now,
            ];

            if (count($buffer) >= 1000) {
                DB::table('lineage_depths')->insert($buffer);
                $written += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('lineage_depths')->insert($buffer);
            $written += count($buffer);
        }

        return $written;
    }

    /**
     * The generation of a person within their family branch, if the branch has
     * a named apical ancestor and the depths have been computed.
     *
     * @return array{root: string, depth: int, min_depth: int, max_depth: int, collapsed: bool}|null
     */
    public function forPerson(Person $person): ?array
    {
        $rootId = DB::table('family_branches')
            ->where('id', $person->family_branch_id)
            ->value('ancestor_person_id');

        if ($rootId === null) {
            return null;
        }

        $row = DB::table('lineage_depths')
            ->where('root_person_id', $rootId)
            ->where('person_id', $person->getKey())
            ->first();

        if ($row === null) {
            return null;
        }

        $rootUlid = DB::table('people')->where('id', $rootId)->value('ulid');

        return [
            'root' => (string) $rootUlid,
            'depth' => (int) $row->depth,
            'min_depth' => (int) $row->min_depth,
            'max_depth' => (int) $row->max_depth,
            // True when the person descends from the founder by lines of
            // different lengths — cousins married somewhere upstream.
            'collapsed' => $row->min_depth !== $row->max_depth,
        ];
    }

    /**
     * The direct line from a person up to their branch's apical ancestor.
     *
     * @return array<int, Person>
     */
    /**
     * The single line from the top of the clan down to one person.
     *
     * lineage() returns every ancestor, which is a tree: two parents, four
     * grandparents, and no way to read it as a list. A family reciting itself
     * recites one chain, so this picks one parent at each step — the one the
     * clan's own descent runs through, then the father, then whoever is there.
     *
     * Ordered from the oldest down, because that is the direction it is said
     * in and the direction the generations count.
     *
     * @return array<int, Person>
     */
    public function directLine(Person $person, int $maxDepth = 40): array
    {
        $clanRoot = $person->clan?->counting_origin_person_id
            ?? $person->clan?->ancestor_person_id;

        $inTheLine = $clanRoot === null
            ? []
            : DB::table('lineage_depths')
                ->where('root_person_id', $clanRoot)
                ->pluck('person_id')
                ->flip()
                ->all();

        $chain = [$person->getKey()];
        $seen = [$person->getKey() => true];
        $at = $person->getKey();

        for ($step = 0; $step < $maxDepth; $step++) {
            $parents = DB::table('family_edges')
                ->join('people', 'people.id', '=', 'family_edges.parent_id')
                ->where('family_edges.child_id', $at)
                ->whereNull('people.deleted_at')
                ->orderByRaw("people.gender = 'male' desc")
                ->pluck('people.id')
                ->all();

            if ($parents === []) {
                break;
            }

            $next = null;

            foreach ($parents as $parent) {
                if (isset($inTheLine[$parent]) && ! isset($seen[$parent])) {
                    $next = $parent;
                    break;
                }
            }

            // Nobody in the clan's own descent: the father, then whoever is
            // recorded. A chain that stops early says less than a chain that
            // guesses, but it never says something untrue.
            $next ??= collect($parents)->first(fn (int $id) => ! isset($seen[$id]));

            if ($next === null) {
                break;
            }

            $chain[] = $next;
            $seen[$next] = true;
            $at = $next;
        }

        $people = Person::whereIn('id', $chain)
            ->with([
                'profileMedia:id,path,conversions',
                'clan:id,ulid,name,ancestor_person_id,counting_origin_person_id,generation_offset',
                'clan.ancestor:id,display_name',
                'clan.countingOrigin:id,display_name',
                'familyBranch:id,ulid,name,ancestor_person_id,generation_offset',
                'familyBranch.ancestor:id,display_name',
                'lineageDepths:person_id,root_person_id,depth',
                'generation',
            ])
            ->get()
            ->keyBy('id');

        return collect(array_reverse($chain))
            ->map(fn (int $id) => $people[$id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function lineage(Person $person, int $maxDepth = 30): array
    {
        $depths = $this->walker->ascend($person->getKey(), $maxDepth);

        return Person::whereIn('id', array_keys($depths))
            ->with(['profileMedia:id,path,conversions', 'generation:id,generation_name'])
            ->get()
            ->sortBy(fn (Person $p) => $depths[$p->getKey()])
            ->values()
            ->all();
    }
}
