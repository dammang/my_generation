<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Media;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Story;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes a tribe and everything recorded under it, permanently.
 *
 * Written for taking demo data out of a live archive, where the alternative is
 * a page of ad-hoc SQL typed against production at the end of a long day.
 *
 * Nothing here is reversible and none of it is soft: the point is that the
 * records are gone. It prints what it is about to destroy and refuses to run
 * without --force, and it runs in one transaction so a failure halfway leaves
 * the archive as it was rather than half a family.
 */
class PurgeTribe extends Command
{
    protected $signature = 'archive:purge-tribe
        {tribe : The tribe id or name}
        {--users=* : Email addresses to remove with it}
        {--force : Actually do it}';

    protected $description = 'Permanently remove a tribe, its families and everything recorded under them';

    public function handle(): int
    {
        $tribe = Tribe::where('id', $this->argument('tribe'))
            ->orWhere('name', $this->argument('tribe'))
            ->first();

        if ($tribe === null) {
            $this->error('No tribe by that id or name.');

            return self::FAILURE;
        }

        $people = Person::withTrashed()->where('tribe_id', $tribe->id)->pluck('id');
        $clans = Clan::where('tribe_id', $tribe->id)->pluck('id');
        $users = User::whereIn('email', $this->option('users'))->get();

        $this->table(['What', 'How many'], [
            ['Tribe', $tribe->name],
            ['People', $people->count()],
            ['Clans', $clans->count()],
            ['Family branches', FamilyBranch::where('tribe_id', $tribe->id)->count()],
            ['Marriages', $this->unions($people)->count()],
            ['Photographs and files', $this->media($people)->count()],
            ['Stories', Story::where('tribe_id', $tribe->id)->count()],
            ['Accounts', $users->pluck('email')->implode(', ') ?: '—'],
        ]);

        if (! $this->option('force')) {
            $this->warn('Nothing was deleted. Add --force to go ahead.');

            return self::SUCCESS;
        }

        // Worked out once, before anything is removed: every one of these
        // lists is derived from rows this is about to delete.
        $unions = $this->unions($people)->pluck('id');
        $mediaIds = $this->media($people)->pluck('id');
        $branches = FamilyBranch::where('tribe_id', $tribe->id)->pluck('id');

        DB::transaction(function () use ($tribe, $people, $clans, $users, $unions, $mediaIds, $branches): void {
            // Anything pointing at a person by morph, which no foreign key
            // knows about and nothing would cascade.
            // Raw deletes throughout: half of these models soft-delete, and a
            // purge that leaves the rows behind is not a purge — it is a
            // foreign key violation two statements later.
            DB::table('media')->whereIn('id', $mediaIds)->delete();

            DB::table('change_requests')
                ->where('target_type', 'person')
                ->whereIn('target_id', $people)
                ->delete();

            DB::table('revisions')
                ->where('revisionable_type', 'person')
                ->whereIn('revisionable_id', $people)
                ->delete();

            DB::table('dispute_claims')
                ->whereIn('dispute_id', DB::table('disputes')
                    ->where('disputable_type', 'person')
                    ->whereIn('disputable_id', $people)
                    ->pluck('id'))
                ->delete();

            DB::table('disputes')
                ->where('disputable_type', 'person')
                ->whereIn('disputable_id', $people)
                ->delete();
            DB::table('citations')
                ->where('citable_type', 'person')
                ->whereIn('citable_id', $people)
                ->delete();

            DB::table('stories')->where('tribe_id', $tribe->id)->delete();

            // Then the restricted references, in the order the keys demand:
            // relationships and marriages hold people, and people hold nothing
            // once those are gone.
            DB::table('relationships')
                ->whereIn('person_id', $people)
                ->orWhereIn('related_person_id', $people)
                ->delete();

            DB::table('union_children')->whereIn('union_id', $unions)->delete();
            DB::table('unions')->whereIn('id', $unions)->delete();

            DB::table('person_merges')
                ->whereIn('winner_person_id', $people)
                ->orWhereIn('loser_person_id', $people)
                ->delete();

            DB::table('people')->whereIn('id', $people)->delete();

            // The organisation above them.
            DB::table('clan_registrations')->where('tribe_id', $tribe->id)->delete();
            DB::table('generations')->where('tribe_id', $tribe->id)->delete();
            DB::table('family_branches')->where('tribe_id', $tribe->id)->delete();

            // Every scope the tribe owns, branches included. A scope left
            // pointing at a family that no longer exists is a permission row
            // nothing can evaluate and nobody can find.
            $scopes = Scope::query()
                ->where(fn ($q) => $q->where('scopeable_type', 'tribe')->where('scopeable_id', $tribe->id))
                ->orWhere(fn ($q) => $q->where('scopeable_type', 'clan')->whereIn('scopeable_id', $clans))
                ->orWhere(fn ($q) => $q->where('scopeable_type', 'family_branch')->whereIn('scopeable_id', $branches))
                ->pluck('id');

            DB::table('scope_role_user')->whereIn('scope_id', $scopes)->delete();
            DB::table('memberships')->whereIn('scope_id', $scopes)->delete();
            DB::table('scopes')->whereIn('id', $scopes)->delete();
            DB::table('clans')->whereIn('id', $clans)->delete();
            DB::table('tribes')->where('id', $tribe->id)->delete();

            foreach ($users as $user) {
                DB::table('change_requests')->where('requested_by', $user->id)->delete();
                DB::table('memberships')->where('user_id', $user->id)->delete();
                DB::table('scope_role_user')->where('user_id', $user->id)->delete();
                DB::table('profile_claims')->where('user_id', $user->id)->delete();
                DB::table('contribution_stats')->where('user_id', $user->id)->delete();
                DB::table('device_tokens')->where('user_id', $user->id)->delete();
                DB::table('users')->where('id', $user->id)->delete();
            }
        });

        $this->info('Gone.');

        return self::SUCCESS;
    }

    /** Every marriage either partner of which is being removed. */
    private function unions($people)
    {
        return Union::withTrashed()
            ->whereIn('partner_1_id', $people)
            ->orWhereIn('partner_2_id', $people)
            ->get();
    }

    /** Photographs hang off people by morph, so no key would cascade them. */
    private function media($people)
    {
        return Media::where('mediable_type', 'person')
            ->whereIn('mediable_id', $people)
            ->get();
    }
}
