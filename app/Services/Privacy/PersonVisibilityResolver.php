<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\PrivacyLevel;
use App\Models\Person;

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

    private function passesLevel(
        ViewerScope $viewer,
        Person $person,
        bool $isFamily,
        bool $isContributor,
    ): bool {
        return match ($person->privacyLevel()) {
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
