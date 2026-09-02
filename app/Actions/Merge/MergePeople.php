<?php

declare(strict_types=1);

namespace App\Actions\Merge;

use App\Enums\DuplicateStatus;
use App\Enums\PersonNameType;
use App\Enums\RevisionAction;
use App\Exceptions\GenealogyRuleException;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonMerge;
use App\Models\PersonName;
use App\Models\Revision;
use App\Models\User;
use App\Services\Graph\GraphSideEffects;
use App\Services\Matching\MatchKeyGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Merges two records for the same person, reversibly.
 *
 * Every foreign key repointed is logged in `moved_records` and the loser is
 * snapshotted in full, so the merge can be replayed backwards. A wrongly merged
 * ancestor is much harder to undo in a family's understanding of itself than a
 * duplicate is to spot, so the operation is designed to be undone.
 *
 * The loser row survives soft-deleted with merged_into_person_id set: old
 * ULIDs, share links and bookmarks still resolve, redirecting to the winner
 * rather than 404ing.
 */
class MergePeople
{
    /**
     * Tables holding a plain reference to a person, and the unique key that a
     * naive repoint would violate.
     *
     * @var array<string, array{column: string, unique: array<int, string>|null}>
     */
    private const SIMPLE_REFERENCES = [
        'person_names' => ['column' => 'person_id', 'unique' => ['normalized', 'type']],
        'person_affiliations' => ['column' => 'person_id', 'unique' => ['tribe_id', 'clan_id']],
        'person_events' => ['column' => 'person_id', 'unique' => null],
        'union_children' => ['column' => 'person_id', 'unique' => ['union_id']],
        'story_people' => ['column' => 'person_id', 'unique' => ['story_id']],
        'saved_people' => ['column' => 'person_id', 'unique' => ['user_id']],
        'profile_claims' => ['column' => 'person_id', 'unique' => ['user_id']],
    ];

    /** Optional pointers where a collision is impossible. */
    private const NULLABLE_POINTERS = [
        'family_branches' => 'ancestor_person_id',
        'sources' => 'informant_person_id',
        'oral_histories' => 'interviewee_person_id',
    ];

    public function __construct(private readonly MatchKeyGenerator $matchKeys) {}

    /**
     * @param  array<string, string>  $fieldChoices  field => 'winner'|'loser'
     */
    public function handle(User $mergedBy, Person $winner, Person $loser, array $fieldChoices = []): PersonMerge
    {
        if ($winner->is($loser)) {
            throw new GenealogyRuleException(
                'A person cannot be merged into themselves.',
                'MERGE_SELF',
            );
        }

        return DB::transaction(function () use ($mergedBy, $winner, $loser, $fieldChoices): PersonMerge {
            $snapshot = $loser->attributesToArray();
            $moved = [];

            $this->applyFieldChoices($winner, $loser, $fieldChoices, $mergedBy);

            // Side effects are suspended for the repoint and re-derived once at
            // the end: a merge touches thousands of rows, and projecting each
            // individually would be both slow and, mid-merge, wrong.
            GraphSideEffects::without(function () use ($winner, $loser, &$moved): void {
                $moved['relationships'] = $this->moveRelationships($winner, $loser);
                $moved['unions'] = $this->moveUnions($winner, $loser);
                $moved['morphs'] = $this->moveMorphs($winner, $loser);

                foreach (self::SIMPLE_REFERENCES as $table => $config) {
                    $moved[$table] = $this->moveSimple($table, $config, $winner, $loser);
                }

                foreach (self::NULLABLE_POINTERS as $table => $column) {
                    $moved[$table] = DB::table($table)
                        ->where($column, $loser->getKey())
                        ->update([$column => $winner->getKey()]);
                }

                // Derived tables are rebuilt rather than repointed.
                DB::table('lineage_depths')->where('person_id', $loser->getKey())->delete();
                DB::table('lineage_depths')->where('root_person_id', $loser->getKey())->delete();
                DB::table('person_match_keys')->where('person_id', $loser->getKey())->delete();
            });

            $this->preserveLoserSpelling($winner, $loser);

            $merge = PersonMerge::create([
                'winner_person_id' => $winner->getKey(),
                'loser_person_id' => $loser->getKey(),
                'field_choices' => $fieldChoices,
                'moved_records' => $moved,
                'loser_snapshot' => $snapshot,
                'merged_by' => $mergedBy->getKey(),
                'merged_at' => now(),
            ]);

            // The tombstone: soft-deleted, but pointing at its successor.
            $loser->merged_into_person_id = $winner->getKey();
            $loser->save();
            $loser->delete();

            $this->rebuildDerived($winner);

            DB::table('duplicate_candidates')
                ->where(fn ($q) => $q->where('person_a_id', $loser->getKey())->orWhere('person_b_id', $loser->getKey()))
                ->update(['status' => DuplicateStatus::Merged->value, 'reviewed_by' => $mergedBy->getKey(), 'reviewed_at' => now()]);

            Revision::create([
                'revisionable_type' => $winner->getMorphClass(),
                'revisionable_id' => $winner->getKey(),
                'action' => RevisionAction::Merged,
                'old_value' => ['merged_person' => $loser->ulid],
                'new_value' => ['moved' => $moved],
                'reason' => "Merged {$loser->display_name} into {$winner->display_name}",
                'changed_by' => $mergedBy->getKey(),
            ]);

            AuditLog::create([
                'user_id' => $mergedBy->getKey(),
                'action' => 'person.merged',
                'auditable_type' => $winner->getMorphClass(),
                'auditable_id' => $winner->getKey(),
                'context' => ['loser' => $loser->ulid, 'merge' => $merge->ulid, 'moved' => $moved],
            ]);

            return $merge;
        });
    }

    /** @param  array<string, string>  $choices */
    private function applyFieldChoices(Person $winner, Person $loser, array $choices, User $mergedBy): void
    {
        $taken = [];

        foreach ($choices as $field => $side) {
            if ($side !== 'loser') {
                continue;
            }

            $winner->setAttribute($field, $loser->getAttribute($field));
            $taken[] = $field;
        }

        // A field the winner lacks and the loser has is taken automatically:
        // the point of merging is to end up with the union of what is known.
        foreach (['birth_date', 'birth_year', 'death_date', 'death_year', 'birth_place_id', 'biography', 'native_name'] as $field) {
            if (in_array($field, $taken, true)) {
                continue;
            }

            if (blank($winner->getAttribute($field)) && filled($loser->getAttribute($field))) {
                $winner->setAttribute($field, $loser->getAttribute($field));
            }
        }

        $winner->withRevisionContext(reason: "Merged from {$loser->display_name}");
        $winner->save();
    }

    /**
     * Parent and child edges both move. Two hazards: an edge that would become
     * self-referential (the loser was the winner's parent, which happens when a
     * record was duplicated *and* mislinked), and one that duplicates an edge
     * the winner already has.
     *
     * @return array<string, int>
     */
    private function moveRelationships(Person $winner, Person $loser): array
    {
        $moved = 0;
        $dropped = 0;

        foreach (['person_id', 'related_person_id'] as $column) {
            $other = $column === 'person_id' ? 'related_person_id' : 'person_id';

            $rows = DB::table('relationships')->where($column, $loser->getKey())->get();

            foreach ($rows as $row) {
                $wouldSelfReference = (int) $row->{$other} === $winner->getKey();

                $duplicate = DB::table('relationships')
                    ->where($column, $winner->getKey())
                    ->where($other, $row->{$other})
                    ->where('relationship_type', $row->relationship_type)
                    ->where('relationship_subtype', $row->relationship_subtype)
                    ->exists();

                if ($wouldSelfReference || $duplicate) {
                    DB::table('relationships')->where('id', $row->id)->delete();
                    $dropped++;

                    continue;
                }

                DB::table('relationships')->where('id', $row->id)->update([$column => $winner->getKey()]);
                $moved++;
            }
        }

        return ['moved' => $moved, 'dropped' => $dropped];
    }

    /**
     * Unions carry a CHECK that partner_1_id < partner_2_id, so a repointed
     * pair has to be renormalised rather than simply updated.
     *
     * @return array<string, int>
     */
    private function moveUnions(Person $winner, Person $loser): array
    {
        $moved = 0;
        $dropped = 0;

        $rows = DB::table('unions')
            ->where(fn ($q) => $q->where('partner_1_id', $loser->getKey())->orWhere('partner_2_id', $loser->getKey()))
            ->get();

        foreach ($rows as $row) {
            $one = (int) $row->partner_1_id === $loser->getKey() ? $winner->getKey() : (int) $row->partner_1_id;
            $two = $row->partner_2_id === null
                ? null
                : ((int) $row->partner_2_id === $loser->getKey() ? $winner->getKey() : (int) $row->partner_2_id);

            // A union of the winner with themselves is an artefact of the merge.
            if ($two !== null && $one === $two) {
                DB::table('unions')->where('id', $row->id)->update([
                    'deleted_at' => now(),
                    'deleted_token' => $row->id,
                ]);
                $dropped++;

                continue;
            }

            [$one, $two] = $two === null || $one < $two ? [$one, $two] : [$two, $one];

            $duplicate = DB::table('unions')
                ->where('partner_1_id', $one)
                ->where('partner_2_id', $two)
                ->where('union_type', $row->union_type)
                ->where('order_index', $row->order_index)
                ->whereNull('deleted_at')
                ->where('id', '!=', $row->id)
                ->exists();

            if ($duplicate) {
                DB::table('unions')->where('id', $row->id)->update([
                    'deleted_at' => now(),
                    'deleted_token' => $row->id,
                ]);
                $dropped++;

                continue;
            }

            DB::table('unions')->where('id', $row->id)->update([
                'partner_1_id' => $one,
                'partner_2_id' => $two,
            ]);
            $moved++;
        }

        return ['moved' => $moved, 'dropped' => $dropped];
    }

    /** @return array<string, int> */
    private function moveMorphs(Person $winner, Person $loser): array
    {
        $moved = [];

        foreach (['citations' => 'citable', 'media' => 'mediable', 'disputes' => 'disputable'] as $table => $morph) {
            $moved[$table] = DB::table($table)
                ->where("{$morph}_type", $loser->getMorphClass())
                ->where("{$morph}_id", $loser->getKey())
                ->update(["{$morph}_id" => $winner->getKey()]);
        }

        return $moved;
    }

    /**
     * @param  array{column: string, unique: array<int, string>|null}  $config
     * @return array<string, int>
     */
    private function moveSimple(string $table, array $config, Person $winner, Person $loser): array
    {
        $column = $config['column'];
        $rows = DB::table($table)->where($column, $loser->getKey())->get();

        $moved = 0;
        $dropped = 0;

        foreach ($rows as $row) {
            $collides = false;

            if ($config['unique'] !== null) {
                $query = DB::table($table)->where($column, $winner->getKey());

                foreach ($config['unique'] as $part) {
                    $query->where($part, $row->{$part});
                }

                $collides = $query->exists();
            }

            $key = property_exists($row, 'id') ? ['id' => $row->id] : (array) $row;

            if ($collides) {
                DB::table($table)->where($key)->delete();
                $dropped++;

                continue;
            }

            DB::table($table)->where($key)->update([$column => $winner->getKey()]);
            $moved++;
        }

        return ['moved' => $moved, 'dropped' => $dropped];
    }

    /**
     * Keeps the loser's spelling as an alternate name on the winner.
     *
     * That spelling is exactly the evidence that made the two records look like
     * the same person. Discarding it loses the reason for the merge, and makes
     * the winner unfindable by the name a contributor originally used.
     */
    private function preserveLoserSpelling(Person $winner, Person $loser): void
    {
        if (blank($loser->display_name) || $loser->display_name === $winner->display_name) {
            return;
        }

        $normalised = mb_strtolower((string) preg_replace('/[^\\p{L}\\p{N}]+/u', '', $loser->display_name));

        PersonName::firstOrCreate(
            [
                'person_id' => $winner->getKey(),
                'normalized' => $normalised,
                'type' => PersonNameType::Alternate,
            ],
            [
                'name' => $loser->display_name,
            ],
        );
    }

    private function rebuildDerived(Person $winner): void
    {
        DB::table('family_edges')
            ->where(fn ($q) => $q->where('parent_id', $winner->getKey())->orWhere('child_id', $winner->getKey()))
            ->delete();

        Artisan::call('genealogy:rebuild-edges');

        $this->matchKeys->regenerateFor($winner->refresh()->load('names'));
    }
}
