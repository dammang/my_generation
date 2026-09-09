<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Person;
use App\Services\Graph\GraphVersion;
use Illuminate\Console\Command;

/**
 * Opens a clan's records to the clan.
 *
 * Every person starts at `family`, which means close kin or their own branch.
 * A clan of four hundred where nobody is above that level is one where being
 * approved into the clan grants nothing: the archive looks empty, search finds
 * nobody, and a new member cannot even find their own record to claim it.
 *
 * Only records still sitting at the default are moved. Anybody who has chosen
 * something for themselves — or had it chosen for them by a reviewer — keeps
 * it: a bulk command that overrode a deliberate answer would be the worst kind
 * of bug in this part of the archive, because nobody would see it happen.
 */
class SetClanVisibility extends Command
{
    protected $signature = 'archive:set-clan-visibility
        {clan : The clan id or name}
        {--level=clan : The level to move them to}
        {--from=family : Only records still at this level are moved}
        {--force : Actually do it}';

    protected $description = "Raise a clan's people from the default privacy level to another";

    public function __construct(private readonly GraphVersion $graphVersion)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $clan = Clan::where('id', $this->argument('clan'))
            ->orWhere('name', $this->argument('clan'))
            ->first();

        if ($clan === null) {
            $this->error('No clan by that id or name.');

            return self::FAILURE;
        }

        $to = PrivacyLevel::tryFrom((string) $this->option('level'));
        $from = PrivacyLevel::tryFrom((string) $this->option('from'));

        if ($to === null || $from === null) {
            $this->error('Levels must be one of: '.collect(PrivacyLevel::cases())
                ->map(fn (PrivacyLevel $level) => $level->value)->implode(', '));

            return self::FAILURE;
        }

        $counts = Person::where('clan_id', $clan->getKey())
            ->selectRaw('privacy_level, count(*) as total')
            ->groupBy('privacy_level')
            ->pluck('total', 'privacy_level');

        $this->table(
            ['Level', 'People'],
            $counts->map(fn ($total, $level) => [
                $level.($level === $from->value ? '  ← moving these' : ''),
                $total,
            ])->values()->all(),
        );

        $moving = (int) ($counts[$from->value] ?? 0);

        $this->line("Moving {$moving} of {$clan->name} from {$from->value} to {$to->value}.");
        $this->line('Everybody else keeps what they have.');

        // Worth saying, because "visible to the clan" sounds broader than it
        // is: a living person's dates, places and biography stay withheld
        // from anybody outside their family whatever this is set to.
        $this->line('Living people keep their dates and places hidden from anybody outside their own family.');

        if (! $this->option('force')) {
            $this->warn('Nothing was changed. Add --force to go ahead.');

            return self::SUCCESS;
        }

        $changed = Person::where('clan_id', $clan->getKey())
            ->where('privacy_level', $from)
            ->update(['privacy_level' => $to]);

        // A mass update fires no observer, and privacy_level is one of the
        // fields a tree card is drawn from: without this, every cached tree
        // goes on being served under the old answer.
        $this->graphVersion->bump($clan->tribe_id);

        $this->info("{$changed} moved to {$to->value}.");

        return self::SUCCESS;
    }
}
