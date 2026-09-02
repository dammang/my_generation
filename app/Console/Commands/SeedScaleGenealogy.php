<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Tribe;
use App\Services\Graph\GraphSideEffects;
use App\Support\NameCorpus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates a large synthetic genealogy for performance work.
 *
 * Local and testing only. Writes with raw bulk inserts and side effects
 * suspended — the point is to produce a realistically shaped graph quickly, not
 * to exercise the write path, which has its own tests.
 *
 * The shape matters more than the size: a real genealogy is wide at the bottom,
 * narrow at the top, with couples and variable sibship sizes. A balanced tree
 * would make traversal look faster than it is.
 */
class SeedScaleGenealogy extends Command
{
    protected $signature = 'genealogy:seed-scale
                            {--people=100000 : Approximate number of people to generate}
                            {--children=4 : Average children per couple}';

    protected $description = 'Generate a large synthetic genealogy for performance testing';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Refusing to run outside local/testing.');

            return self::FAILURE;
        }

        $target = (int) $this->option('people');
        $avgChildren = (float) $this->option('children');

        $tribe = Tribe::firstOrCreate(['slug' => 'scale-test'], ['name' => 'Scale Test']);
        $clan = Clan::firstOrCreate(
            ['tribe_id' => $tribe->id, 'slug' => 'scale-clan'],
            ['name' => 'Scale Clan'],
        );

        $started = microtime(true);

        GraphSideEffects::without(function () use ($target, $avgChildren, $tribe, $clan): void {
            $this->generate($target, $avgChildren, $tribe->id, $clan->id);
        });

        $this->info(sprintf('Generated in %.1fs.', microtime(true) - $started));

        $this->call('genealogy:rebuild-edges', ['--fresh' => true]);

        $branch = FamilyBranch::firstOrCreate(
            ['tribe_id' => $tribe->id, 'slug' => 'scale-branch'],
            ['name' => 'Scale Branch', 'clan_id' => $clan->id],
        );

        // The first founder who actually had children: 15% of the seeded
        // population never marries, and rooting the lineage at a childless
        // person would make the generation numbers meaningless.
        $founder = DB::table('family_edges')
            ->join('people', 'people.id', '=', 'family_edges.parent_id')
            ->where('people.tribe_id', $tribe->id)
            ->orderBy('people.id')
            ->value('family_edges.parent_id');
        $branch->forceFill(['ancestor_person_id' => $founder])->save();

        $this->table(['metric', 'value'], [
            ['people', DB::table('people')->count()],
            ['relationships', DB::table('relationships')->count()],
            ['unions', DB::table('unions')->count()],
            ['family_edges', DB::table('family_edges')->count()],
        ]);

        return self::SUCCESS;
    }

    private function generate(int $target, float $avgChildren, int $tribeId, int $clanId): void
    {
        $year = 1700;
        $generation = [];

        // Several unrelated founding couples, so the graph is a forest rather
        // than one improbably fertile ancestor.
        $founders = max(2, (int) ($target / 20000));
        $generation = $this->insertPeople($founders * 2, $year, $tribeId, $clanId);

        $total = count($generation);

        while ($total < $target) {
            $year += 28;
            $next = [];

            // Pair the generation off. Some people never marry, which is what
            // stops the tree being a perfect binary fan.
            shuffle($generation);
            $couples = [];

            for ($i = 0; $i + 1 < count($generation); $i += 2) {
                if (mt_rand(1, 100) <= 85) {
                    $couples[] = [$generation[$i], $generation[$i + 1]];
                }
            }

            if ($couples === []) {
                break;
            }

            // Flushed in batches rather than accumulated across the whole
            // generation: a wide generation is tens of thousands of rows, and
            // holding them all is how this ran out of memory.
            foreach (array_chunk($couples, 250) as $batch) {
                if ($total >= $target) {
                    break;
                }

                $next = [...$next, ...$this->breedBatch($batch, $year, $avgChildren, $tribeId, $clanId, $total)];
            }

            $this->output->write("\r  generation ".$year.': '.number_format($total).' people   ');

            $generation = $next;
        }

        $this->output->writeln('');
    }

    /**
     * One batch of couples: their unions, children, parent edges and grouping
     * rows, written and released before the next batch is built.
     *
     * @param  array<int, array{0: int, 1: int}>  $couples
     * @return array<int, int> the children produced
     */
    private function breedBatch(
        array $couples,
        int $year,
        float $avgChildren,
        int $tribeId,
        int $clanId,
        int &$total,
    ): array {
        $counts = [];
        $childTotal = 0;

        foreach ($couples as $index => $_) {
            $counts[$index] = max(1, (int) round($avgChildren + mt_rand(-2, 2)));
            $childTotal += $counts[$index];
        }

        $childIds = $this->insertPeople($childTotal, $year, $tribeId, $clanId);

        $unionRows = [];
        foreach ($couples as [$a, $b]) {
            $unionRows[] = [
                'ulid' => (string) Str::ulid(),
                'partner_1_id' => min($a, $b),
                'partner_2_id' => max($a, $b),
                'union_type' => 'marriage',
                'status' => 'unknown',
                'order_index' => 1,
                'marriage_date_precision' => 'unknown',
                'marriage_year' => $year - 25,
                'verification_status' => 'unverified',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $firstUnionId = DB::table('unions')->insertGetId($unionRows[0]);

        if (count($unionRows) > 1) {
            DB::table('unions')->insert(array_slice($unionRows, 1));
        }

        $relationshipRows = [];
        $childRows = [];
        $cursor = 0;

        foreach ($couples as $index => [$a, $b]) {
            $unionId = $firstUnionId + $index;

            for ($order = 1; $order <= $counts[$index]; $order++) {
                $childId = $childIds[$cursor++];

                $childRows[] = [
                    'union_id' => $unionId,
                    'person_id' => $childId,
                    'relationship_type' => 'biological',
                    'birth_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach ([$a, $b] as $parentId) {
                    $relationshipRows[] = [
                        'ulid' => (string) Str::ulid(),
                        'person_id' => $parentId,
                        'related_person_id' => $childId,
                        'relationship_type' => 'parent_child',
                        'relationship_subtype' => 'biological',
                        'is_biological' => 1,
                        'union_id' => $unionId,
                        'certainty' => 'probable',
                        'date_precision' => 'unknown',
                        'verification_status' => 'unverified',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($relationshipRows, 1000) as $chunk) {
            DB::table('relationships')->insert($chunk);
        }

        foreach (array_chunk($childRows, 1000) as $chunk) {
            DB::table('union_children')->insert($chunk);
        }

        $total += $childTotal;

        return $childIds;
    }

    /**
     * Bulk-inserts a run of people and returns their ids.
     *
     * Relies on InnoDB allocating contiguous auto-increment values for a single
     * multi-row insert, which holds for a single-threaded seeder.
     *
     * @return array<int, int>
     */
    private function insertPeople(int $count, int $year, int $tribeId, int $clanId): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $given = NameCorpus::MALE_GIVEN[array_rand(NameCorpus::MALE_GIVEN)];
            $second = NameCorpus::SECOND_ELEMENT[array_rand(NameCorpus::SECOND_ELEMENT)];
            $birth = $year + mt_rand(-3, 3);

            $rows[] = [
                'ulid' => (string) Str::ulid(),
                'first_name' => $given,
                'last_name' => $second,
                'display_name' => "{$given} {$second}",
                'sort_name' => Str::lower("{$second} {$given}"),
                'gender' => mt_rand(0, 1) ? 'male' : 'female',
                'birth_date' => sprintf('%04d-01-01', $birth),
                'birth_date_precision' => 'year',
                'death_date_precision' => 'unknown',
                'birth_year' => $birth,
                'is_living' => $birth > (int) date('Y') - 90 ? 1 : 0,
                'privacy_level' => 'tribe',
                'verification_status' => 'unverified',
                'tribe_id' => $tribeId,
                'clan_id' => $clanId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $first = DB::table('people')->insertGetId($rows[0]);

        foreach (array_chunk(array_slice($rows, 1), 1000) as $chunk) {
            DB::table('people')->insert($chunk);
        }

        return range($first, $first + $count - 1);
    }
}
