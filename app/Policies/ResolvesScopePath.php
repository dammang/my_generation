<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Scope;
use App\Models\Tribe;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds the scope path a record sits at, for prefix-matched authorization.
 *
 * The most specific placement wins: a person in a family branch is authorised
 * against that branch's path, which a clan or tribe admin's shorter path is a
 * prefix of — so authority still flows downward without extra lookups.
 */
trait ResolvesScopePath
{
    /** @var array<string, string|null> */
    private static array $scopePathCache = [];

    protected function scopePathFor(Model $record): ?string
    {
        $placements = [
            ['family_branch', $record->getAttribute('family_branch_id') ?? ($record instanceof FamilyBranch ? $record->getKey() : null)],
            ['clan', $record->getAttribute('clan_id') ?? ($record instanceof Clan ? $record->getKey() : null)],
            ['tribe', $record->getAttribute('tribe_id') ?? ($record instanceof Tribe ? $record->getKey() : null)],
        ];

        foreach ($placements as [$type, $id]) {
            if ($id === null) {
                continue;
            }

            $path = $this->lookupScopePath($type, (int) $id);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * The scope record itself, for writes that must remember where they belong.
     *
     * A change request filed without a scope can only ever be reviewed by
     * somebody with tribe-wide authority — a clan's reviewer would open an
     * empty queue and conclude the feature was broken.
     */
    protected function scopeFor(Model $record): ?Scope
    {
        $path = $this->scopePathFor($record);

        return $path === null ? null : Scope::where('path', $path)->first();
    }

    private function lookupScopePath(string $type, int $id): ?string
    {
        $key = "{$type}:{$id}";

        if (array_key_exists($key, self::$scopePathCache)) {
            return self::$scopePathCache[$key];
        }

        return self::$scopePathCache[$key] = Scope::query()
            ->where('scopeable_type', $type)
            ->where('scopeable_id', $id)
            ->value('path');
    }
}
