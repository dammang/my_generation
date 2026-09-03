<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Person;
use App\Models\Relationship;
use App\Services\Graph\FamilyEdgeProjector;
use App\Services\Graph\GraphSideEffects;
use App\Services\Graph\GraphVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Derived person attributes and the counters that depend on them.
 *
 * display_name and sort_name are maintained here rather than computed on read,
 * because both are indexed and drive ordering and search — recomputing them per
 * row at query time would defeat the index.
 */
class PersonObserver
{
    /** Columns whose change alters what a tree card renders. */
    private const CARD_FIELDS = [
        'display_name', 'native_name', 'nickname', 'gender',
        'birth_date', 'birth_year', 'death_date', 'death_year',
        'is_living', 'privacy_level', 'verification_status',
        'profile_media_id', 'has_open_dispute',
    ];

    public function __construct(private readonly GraphVersion $graphVersion) {}

    public function saving(Person $person): void
    {
        if (blank($person->display_name)) {
            $person->display_name = $this->composeDisplayName($person);
        }

        $person->sort_name = $this->composeSortName($person);

        // Authoritative, so the flag cannot drift from the dates it summarises.
        // isDeceased() treats a person with no dates at all as living.
        $person->is_living = ! $person->isDeceased();
    }

    public function created(Person $person): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->adjustCounts($person, +1);
        $this->graphVersion->bump($person->tribe_id);
    }

    public function updated(Person $person): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        foreach (['tribe_id' => 'tribes', 'clan_id' => 'clans', 'family_branch_id' => 'family_branches'] as $column => $table) {
            if (! $person->wasChanged($column)) {
                continue;
            }

            $this->adjust($table, $person->getOriginal($column), -1);
            $this->adjust($table, $person->getAttribute($column), +1);
        }

        if ($person->wasChanged(self::CARD_FIELDS)) {
            $this->graphVersion->bump($person->tribe_id);
        }

        if ($person->wasChanged('tribe_id')) {
            $this->graphVersion->bump($person->getOriginal('tribe_id'));
        }
    }

    public function deleted(Person $person): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->adjustCounts($person, -1);

        // family_edges is a cache of the truth, not the truth. Leaving a
        // deleted person's edges behind means the tree keeps drawing them as
        // somebody's child — and nothing reports an error, because from the
        // traversal's point of view the graph is perfectly consistent.
        DB::table('family_edges')
            ->where('parent_id', $person->getKey())
            ->orWhere('child_id', $person->getKey())
            ->delete();

        $this->graphVersion->bump($person->tribe_id);
    }

    public function restored(Person $person): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->adjustCounts($person, +1);

        // Rebuilt from the relationships that survived, rather than from a
        // record of what was removed: a relationship deleted while the person
        // was gone must not come back with them.
        $this->reprojectEdges($person);

        $this->graphVersion->bump($person->tribe_id);
    }

    /**
     * Re-derives this person's edges from their surviving relationships.
     */
    private function reprojectEdges(Person $person): void
    {
        $relationships = Relationship::query()
            ->where(fn ($q) => $q->where('person_id', $person->getKey())
                ->orWhere('related_person_id', $person->getKey()))
            ->get();

        $projector = app(FamilyEdgeProjector::class);

        foreach ($relationships as $relationship) {
            $projector->project($relationship);
        }
    }

    /** "Thawng Dam" — falls back through native name and nickname. */
    private function composeDisplayName(Person $person): string
    {
        $latin = trim(implode(' ', array_filter([
            $person->first_name,
            $person->middle_name,
            $person->last_name,
        ])));

        return $latin !== ''
            ? $latin
            : (string) ($person->native_name ?? $person->nickname ?? 'Unknown');
    }

    /**
     * ASCII-folded and lowercased so ordering is stable regardless of script or
     * diacritics — "Ṭhawng" and "Thawng" must sort together.
     */
    private function composeSortName(Person $person): string
    {
        $basis = trim(implode(' ', array_filter([
            $person->last_name,
            $person->first_name,
            $person->middle_name,
        ]))) ?: $person->display_name;

        return Str::lower(Str::ascii($basis));
    }

    private function adjustCounts(Person $person, int $delta): void
    {
        $this->adjust('tribes', $person->tribe_id, $delta);
        $this->adjust('clans', $person->clan_id, $delta);
        $this->adjust('family_branches', $person->family_branch_id, $delta);
    }

    private function adjust(string $table, ?int $id, int $delta): void
    {
        if ($id === null) {
            return;
        }

        DB::table($table)->where('id', $id)->update([
            'people_count' => DB::raw('GREATEST(CAST(people_count AS SIGNED) + '.$delta.', 0)'),
        ]);
    }
}
