<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\PrivacyLevel;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Decides what a viewer may see of a person.
 *
 * Two questions, deliberately separate:
 *
 *   1. May the record be seen at all? Answered here AND pushed into SQL by
 *      Person::scopeVisibleTo, because post-filtering a paginated list
 *      produces short pages and leaks counts.
 *   2. Which of its fields survive? Answered here, applied by PersonResource.
 *
 * Living people are handled strictly and fail closed: a person with no dates is
 * treated as living, because the alternative is publishing a living person's
 * details on the strength of missing data.
 */
class PersonVisibilityResolver
{
    /** @var array<string, FieldMask> */
    private array $memo = [];

    /** @var array<int, PrivacyLevel> */
    private array $tribeDefaults = [];

    public function mask(ViewerScope $viewer, Person $person): FieldMask
    {
        $key = $viewer->hash().':'.$person->getKey();

        return $this->memo[$key] ??= $this->resolve($viewer, $person);
    }

    public function canView(ViewerScope $viewer, Person $person): bool
    {
        return $this->mask($viewer, $person)->visible;
    }

    private function resolve(ViewerScope $viewer, Person $person): FieldMask
    {
        if ($viewer->isSuperAdmin) {
            return FieldMask::full();
        }

        // Administering the record's tribe, clan or branch carries the record.
        $administers = $viewer->administersPlacement(
            $person->tribe_id,
            $person->clan_id,
            $person->family_branch_id,
        );

        if ($administers) {
            return FieldMask::full();
        }

        $isSelf = $viewer->personId !== null && $viewer->personId === $person->getKey();
        $isContributor = $viewer->userId !== null && $viewer->userId === $person->created_by;
        $isFamily = $isSelf
            || $viewer->isKin($person->getKey())
            || $viewer->belongsToBranch($person->family_branch_id);

        if (! $this->passesLevel($viewer, $person, $isFamily, $isContributor)) {
            return FieldMask::hidden();
        }

        // A minor's record is never exposed beyond the family scope, whatever
        // its privacy_level says.
        if ($person->isMinor() && ! $isFamily && ! $isContributor) {
            return FieldMask::hidden();
        }

        if ($person->isDeceased()) {
            return FieldMask::full();
        }

        // Living from here down.
        return match (true) {
            $isSelf => FieldMask::full(),
            $isFamily => FieldMask::livingSummary(),
            $isContributor => FieldMask::livingSummary(),
            default => FieldMask::livingLimited(),
        };
    }

    /**
     * Which level actually applies to this record now.
     *
     * A person's own choice governs while they are living. Once a death is
     * recorded it lifts to the archive's default, because a genealogy is read
     * generations after it is written and a permanent lock would leave the
     * tree full of nodes nobody will ever be able to read.
     *
     * Lifting never tightens: somebody who chose to be public stays public
     * after they die. It relaxes a restriction, it does not impose one.
     */
    private function levelFor(Person $person): PrivacyLevel
    {
        $own = $person->privacyLevel();

        if (! $person->isDeceased()) {
            return $own;
        }

        $default = $this->tribeDefault($person->tribe_id);

        return $own->isAtLeastAsStrictAs($default) ? $default : $own;
    }

    /**
     * Read once per tribe per request. The resolver runs for every node of a
     * tree, and a relation touched per node is a query per node.
     */
    private function tribeDefault(?int $tribeId): PrivacyLevel
    {
        $fallback = PrivacyLevel::from(config('genealogy.privacy.default_person_level'));

        if ($tribeId === null) {
            return $fallback;
        }

        return $this->tribeDefaults[$tribeId] ??= PrivacyLevel::tryFrom(
            (string) DB::table('tribes')->where('id', $tribeId)->value('default_privacy_level')
        ) ?? $fallback;
    }

    private function passesLevel(
        ViewerScope $viewer,
        Person $person,
        bool $isFamily,
        bool $isContributor,
    ): bool {
        return match ($this->levelFor($person)) {
            PrivacyLevel::Public => true,

            PrivacyLevel::Tribe => $viewer->belongsToTribe($person->tribe_id)
                || $isFamily || $isContributor,

            PrivacyLevel::Clan => $viewer->belongsToClan($person->clan_id)
                || $isFamily || $isContributor,

            PrivacyLevel::Family => $isFamily || $isContributor,

            PrivacyLevel::Private => $isContributor
                || ($viewer->personId !== null && $viewer->personId === $person->getKey()),
        };
    }
}
