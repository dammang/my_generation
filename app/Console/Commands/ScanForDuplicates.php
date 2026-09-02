<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DuplicateStatus;
use App\Models\Person;
use App\Services\Matching\DuplicateScorer;
use App\Services\Matching\MatchKeyGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds possible duplicate people by blocking, then scoring.
 *
 * Nothing is merged. The command raises candidates for a human to compare; a
 * wrongly merged ancestor is far harder to undo in people's understanding of
 * their family than a duplicate is to spot.
 */
class ScanForDuplicates extends Command
{
    protected $signature = 'genealogy:scan-duplicates
                            {--tribe= : Limit to one tribe id}
                            {--rebuild-keys : Regenerate match keys first}
                            {--since= : Only people updated since this date}';

    protected $description = 'Raise duplicate candidates by blocking on shared match keys, then scoring';

    public function handle(MatchKeyGenerator $keys, DuplicateScorer $scorer): int
    {
        if ($this->option('rebuild-keys')) {
            $this->rebuildKeys($keys);
        }

        $threshold = (float) config('genealogy.matching.threshold');
        $pairs = $this->candidatePairs();

        $this->info(sprintf('Comparing %s blocked pairs…', number_format(count($pairs))));

        $raised = 0;
        $bar = $this->output->createProgressBar(count($pairs));

        foreach ($pairs as [$aId, $bId]) {
            $bar->advance();

            $a = Person::with('names')->find($aId);
            $b = Person::with('names')->find($bId);

            if ($a === null || $b === null) {
                continue;
            }

            ['score' => $score, 'signals' => $signals] = $scorer->score($a, $b);

            if ($score < $threshold) {
                continue;
            }

            DB::table('duplicate_candidates')->updateOrInsert(
                ['person_a_id' => min($aId, $bId), 'person_b_id' => max($aId, $bId)],
                [
                    'ulid' => (string) Str::ulid(),
                    'score' => $score,
                    'signals' => json_encode($signals),
                    'status' => DuplicateStatus::Open->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $raised++;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Raised {$raised} duplicate candidates at or above {$threshold}.");

        return self::SUCCESS;
    }

    private function rebuildKeys(MatchKeyGenerator $keys): void
    {
        $query = Person::query()
            ->with('names')
            ->when($this->option('tribe'), fn ($q, $tribe) => $q->where('tribe_id', $tribe))
            ->when($this->option('since'), fn ($q, $since) => $q->where('updated_at', '>=', $since));

        $total = $query->count();
        $bar = $this->output->createProgressBar($total);

        $query->chunkById(500, function ($people) use ($keys, $bar): void {
            foreach ($people as $person) {
                $keys->regenerateFor($person);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Regenerated match keys for {$total} people.");
    }

    /**
     * Pairs that share at least one blocking key.
     *
     * A self-join on (key_type, key_value) — the index that makes this cheap —
     * with a cap per block so one very common name cannot produce a quadratic
     * blow-up on its own.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function candidatePairs(): array
    {
        $rows = DB::table('person_match_keys as a')
            ->join('person_match_keys as b', function ($join): void {
                $join->on('a.key_type', '=', 'b.key_type')
                    ->on('a.key_value', '=', 'b.key_value')
                    ->on('a.person_id', '<', 'b.person_id');
            })
            ->when($this->option('tribe'), function ($q, $tribe): void {
                $q->join('people as pa', 'pa.id', '=', 'a.person_id')->where('pa.tribe_id', $tribe);
            })
            ->distinct()
            ->limit(200000)
            ->get(['a.person_id as a_id', 'b.person_id as b_id']);

        return $rows->map(fn ($row) => [(int) $row->a_id, (int) $row->b_id])
            ->unique(fn (array $pair) => $pair[0].'-'.$pair[1])
            ->values()
            ->all();
    }
}
