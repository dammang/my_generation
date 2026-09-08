<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Genealogy\AnchorFamilyBranch;
use App\Models\FamilyBranch;
use App\Models\Person;
use Illuminate\Console\Command;

/**
 * Tells a family branch who it descends from, and makes its membership agree.
 *
 * Written because a branch can be created with the wrong founder and there is
 * nothing about the result that looks wrong: the branch named Thawng Dam was
 * rooted at Pu Zo, so every one of Pu Zo's 283 descendants was reported as
 * Thawng Dam's family, on their own profiles, with no error anywhere.
 */
class AnchorBranch extends Command
{
    protected $signature = 'archive:anchor-branch
        {branch : The branch id or name}
        {--ancestor= : The founder\'s person id, if it is being changed}
        {--force : Actually do it}';

    protected $description = 'Set which person a family branch descends from, and resync who is in it';

    public function __construct(private readonly AnchorFamilyBranch $anchor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $branch = FamilyBranch::where('id', $this->argument('branch'))
            ->orWhere('name', $this->argument('branch'))
            ->first();

        if ($branch === null) {
            $this->error('No family branch by that id or name.');

            return self::FAILURE;
        }

        $ancestorId = $this->option('ancestor') === null
            ? $branch->ancestor_person_id
            : (int) $this->option('ancestor');

        $ancestor = $ancestorId === null ? null : Person::find($ancestorId);

        if ($ancestorId !== null && $ancestor === null) {
            $this->error('No person with id '.$ancestorId.'.');

            return self::FAILURE;
        }

        $this->table(['What', 'Now'], [
            ['Branch', $branch->name],
            ['Descends from', $this->describe(Person::find($branch->ancestor_person_id))],
            ['Will descend from', $this->describe($ancestor)],
            ['People in it', (string) Person::where('family_branch_id', $branch->getKey())->count()],
            ['Counter says', (string) $branch->people_count],
        ]);

        if (! $this->option('force')) {
            $this->warn('Nothing was changed. Add --force to go ahead.');

            return self::SUCCESS;
        }

        $branch->forceFill(['ancestor_person_id' => $ancestorId])->save();

        $placed = $this->anchor->handle($branch);

        $branch->refresh();

        $this->table(['What', 'After'], [
            ['Descends from', $this->describe($ancestor)],
            ['People in it', (string) Person::where('family_branch_id', $branch->getKey())->count()],
            ['Counter says', (string) $branch->people_count],
            ['Generation offset', (string) ($branch->generation_offset ?? '—')],
            ['Gained', (string) $placed],
        ]);

        return self::SUCCESS;
    }

    private function describe(?Person $person): string
    {
        return $person === null ? '—' : $person->display_name.' (#'.$person->getKey().')';
    }
}
