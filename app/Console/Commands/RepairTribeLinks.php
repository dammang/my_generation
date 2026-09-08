<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;

/**
 * Attaches people to the tribe their own clan belongs to.
 *
 * A clan sits inside a tribe, so everybody in that clan is in that tribe —
 * but the columns are independent and an import that filled one and not the
 * other leaves people in a family that belongs to a tribe they do not.
 *
 * Nothing visibly breaks, which is the problem: permissions granted at the
 * tribe are evaluated against the person's own tribe_id, so a tribe admin
 * quietly has no authority over three hundred people in a clan they run, and
 * the tribe's own count of itself reads zero.
 *
 * Saved through the model rather than updated in bulk: the counters on tribes
 * and clans and the graph version clients poll are all maintained by the
 * observer, and a bulk update would leave every one of them wrong.
 */
class RepairTribeLinks extends Command
{
    protected $signature = 'archive:repair-tribe-links {--force : Actually do it}';

    protected $description = 'Give people the tribe their clan belongs to, where it is missing';

    public function handle(): int
    {
        $orphans = Person::withTrashed()
            ->whereNull('tribe_id')
            ->whereNotNull('clan_id')
            ->whereHas('clan')
            ->count();

        $this->line("People in a clan but no tribe: {$orphans}");

        if ($orphans === 0) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Nothing was changed. Add --force to go ahead.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($orphans);
        $fixed = 0;

        Person::withTrashed()
            ->whereNull('tribe_id')
            ->whereNotNull('clan_id')
            ->with('clan:id,tribe_id')
            ->chunkById(100, function ($people) use (&$fixed, $bar): void {
                foreach ($people as $person) {
                    if ($person->clan?->tribe_id === null) {
                        continue;
                    }

                    $person->tribe_id = $person->clan->tribe_id;
                    $person->save();

                    $fixed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Attached {$fixed} people to their clan's tribe.");

        return self::SUCCESS;
    }
}
