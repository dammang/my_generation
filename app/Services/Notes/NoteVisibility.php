<?php

declare(strict_types=1);

namespace App\Services\Notes;

use App\Enums\NoteAudience;
use App\Models\Note;
use App\Services\Privacy\ViewerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who may read a note.
 *
 * One place, and both halves of it: the question answered per note, and the
 * same question pushed into SQL so a listing never returns one it would then
 * have to hide. Two implementations of one rule is how this archive has been
 * bitten before — a row the query returned and the policy refused.
 *
 * There is no administrator override. "Only me" means only me: a scale that
 * quietly admits somebody else is not the scale it says it is, and a family
 * archive is not a place where a note about a person should be readable by
 * whoever happens to run the clan.
 */
class NoteVisibility
{
    public function canRead(ViewerScope $viewer, Note $note): bool
    {
        if ($viewer->userId !== null && $viewer->userId === $note->author_id) {
            return true;
        }

        return match ($note->audience) {
            NoteAudience::Private => false,

            // Descended from the writer. Read from lineage_depths, which is
            // kept up to date for every root anybody counts from — writing a
            // note of this kind makes the author one.
            NoteAudience::Descendants => $note->author_person_id !== null
                && $viewer->personId !== null
                && $this->descends($viewer->personId, $note->author_person_id),

            NoteAudience::Family => $note->author_person_id !== null
                && $viewer->isKin($note->author_person_id),

            NoteAudience::Clan => $note->person !== null
                && $viewer->belongsToClan($note->person->clan_id),
        };
    }

    /**
     * The SQL half. Kept beside the PHP half deliberately: they answer the
     * same question and must not drift.
     */
    public function scope(Builder $query, ViewerScope $viewer): Builder
    {
        return $query->where(function (Builder $query) use ($viewer): void {
            if ($viewer->userId !== null) {
                $query->orWhere('author_id', $viewer->userId);
            }

            if ($viewer->personId !== null) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('audience', NoteAudience::Descendants)
                    ->whereIn('author_person_id', DB::table('lineage_depths')
                        ->where('person_id', $viewer->personId)
                        ->select('root_person_id')));
            }

            if ($viewer->kinPersonIds !== []) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('audience', NoteAudience::Family)
                    ->whereIn('author_person_id', $viewer->kinPersonIds));
            }

            if ($viewer->clanIds !== []) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('audience', NoteAudience::Clan)
                    ->whereIn('person_id', DB::table('people')
                        ->whereIn('clan_id', $viewer->clanIds)
                        ->select('id')));
            }

            // Nothing matched means nothing is readable, which an empty
            // nested where would turn into "everything".
            $query->orWhereRaw('1 = 0');
        });
    }

    private function descends(int $personId, int $rootId): bool
    {
        return DB::table('lineage_depths')
            ->where('root_person_id', $rootId)
            ->where('person_id', $personId)
            ->where('depth', '>', 0)
            ->exists();
    }
}
