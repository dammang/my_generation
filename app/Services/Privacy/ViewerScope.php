<?php

declare(strict_types=1);

namespace App\Services\Privacy;

/**
 * Everything the server needs to decide what one requester may see, resolved
 * once per request and cached.
 *
 * The hash is the important part: it goes into every cached tree and person
 * key, so a cached payload can never be served across a permission boundary.
 * Two users see the same cache entry only if their entitlements are identical.
 */
final readonly class ViewerScope
{
    /**
     * @param  array<int, int>  $tribeIds  Tribes with an active membership
     * @param  array<int, int>  $clanIds
     * @param  array<int, int>  $branchIds
     * @param  array<int, string>  $adminScopePaths  Prefix-matched, e.g. ['/1/', '/1/14/']
     * @param  array<int, int>  $kinPersonIds  Close kin of the viewer's claimed person
     * @param  array<int, string>  $permissions  Global permission names
     * @param  array<int, int>  $adminTribeIds  Expanded from adminScopePaths, so the
     *                                          privacy predicate stays expressible in SQL
     * @param  array<int, int>  $adminClanIds
     * @param  array<int, int>  $adminBranchIds
     */
    public function __construct(
        public ?int $userId = null,
        public ?int $personId = null,
        public array $tribeIds = [],
        public array $clanIds = [],
        public array $branchIds = [],
        public array $adminScopePaths = [],
        public array $adminTribeIds = [],
        public array $adminClanIds = [],
        public array $adminBranchIds = [],
        public array $kinPersonIds = [],
        public array $permissions = [],
        public bool $isSuperAdmin = false,
    ) {}

    /**
     * Cache shape version. Bump when the constructor changes so old entries are
     * ignored rather than rehydrated into the wrong shape.
     */
    public const CACHE_VERSION = 1;

    /**
     * Cached as primitives, never as a serialized object.
     *
     * A serialized domain object in a shared cache is poisoned by any change to
     * the class: entries written before a deploy come back as
     * __PHP_Incomplete_Class and every request 500s until the cache is cleared
     * by hand. Primitives survive.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'v' => self::CACHE_VERSION,
            'userId' => $this->userId,
            'personId' => $this->personId,
            'tribeIds' => $this->tribeIds,
            'clanIds' => $this->clanIds,
            'branchIds' => $this->branchIds,
            'adminScopePaths' => $this->adminScopePaths,
            'adminTribeIds' => $this->adminTribeIds,
            'adminClanIds' => $this->adminClanIds,
            'adminBranchIds' => $this->adminBranchIds,
            'kinPersonIds' => $this->kinPersonIds,
            'permissions' => $this->permissions,
            'isSuperAdmin' => $this->isSuperAdmin,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): ?self
    {
        if (($data['v'] ?? null) !== self::CACHE_VERSION) {
            return null;
        }

        return new self(
            userId: $data['userId'],
            personId: $data['personId'],
            tribeIds: $data['tribeIds'],
            clanIds: $data['clanIds'],
            branchIds: $data['branchIds'],
            adminScopePaths: $data['adminScopePaths'],
            adminTribeIds: $data['adminTribeIds'],
            adminClanIds: $data['adminClanIds'],
            adminBranchIds: $data['adminBranchIds'],
            kinPersonIds: $data['kinPersonIds'],
            permissions: $data['permissions'],
            isSuperAdmin: $data['isSuperAdmin'],
        );
    }

    /** An unauthenticated requester: public records only. */
    public static function guest(): self
    {
        return new self;
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    /** Does this viewer administer the scope at the given path, or any above it? */
    public function administers(?string $scopePath): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if ($scopePath === null) {
            return false;
        }

        foreach ($this->adminScopePaths as $adminPath) {
            if (str_starts_with($scopePath, $adminPath)) {
                return true;
            }
        }

        return false;
    }

    public function isKin(?int $personId): bool
    {
        return $personId !== null && in_array($personId, $this->kinPersonIds, true);
    }

    public function belongsToTribe(?int $tribeId): bool
    {
        return $tribeId !== null && in_array($tribeId, $this->tribeIds, true);
    }

    public function belongsToClan(?int $clanId): bool
    {
        return $clanId !== null && in_array($clanId, $this->clanIds, true);
    }

    public function belongsToBranch(?int $branchId): bool
    {
        return $branchId !== null && in_array($branchId, $this->branchIds, true);
    }

    /** Does the viewer administer the tribe, clan or branch this record sits in? */
    public function administersPlacement(?int $tribeId, ?int $clanId, ?int $branchId): bool
    {
        return $this->isSuperAdmin
            || ($tribeId !== null && in_array($tribeId, $this->adminTribeIds, true))
            || ($clanId !== null && in_array($clanId, $this->adminClanIds, true))
            || ($branchId !== null && in_array($branchId, $this->adminBranchIds, true));
    }

    /**
     * A stable fingerprint of this viewer's entitlements. Part of every cache
     * key that can contain person data.
     */
    public function hash(): string
    {
        return substr(hash('xxh128', serialize([
            $this->isSuperAdmin,
            $this->personId,
            $this->tribeIds,
            $this->clanIds,
            $this->branchIds,
            $this->adminScopePaths,
            $this->adminTribeIds,
            $this->adminClanIds,
            $this->adminBranchIds,
            $this->kinPersonIds,
            $this->permissions,
        ])), 0, 16);
    }
}
