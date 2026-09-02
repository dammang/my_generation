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
