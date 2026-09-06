<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * Only ever serialised for the account's own owner — email and status are not
 * public. Other people's accounts are never exposed by the API at all; a
 * contributor is shown by their display name on the record they contributed.
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Resolved for the account being serialised, not for whoever the
        // request happens to be authenticated as.
        //
        // The container binds ViewerScope from $request->user(), and during
        // sign-in there is no such user yet: credentials are still being
        // checked, so the request is a guest. Reading the bound scope there
        // returned an empty one, and every successful login handed the app an
        // account with no tribes and no permissions — which the app then
        // believed until something happened to call /auth/me. This resource is
        // only ever serialised for its own owner, so asking about that owner
        // is both correct and the same answer everywhere else.
        $scope = app(ViewerScopeResolver::class)->resolve($this->resource);

        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'status' => $this->status->value,
            'email_verified' => $this->email_verified_at !== null,
            'is_super_admin' => (bool) $this->is_super_admin,

            // The genealogy record this account has been verified as, if any.
            // Usually null: most people in the database never had an account.
            // Masked for this account, not for the request. At sign-in the
            // request is still a guest, and the ambient viewer would hide the
            // person's own record from them.
            'person' => $this->whenLoaded('person', fn () => PersonResource::maskedFor($this->person, $scope)),

            'scopes' => [
                'tribe_ids' => $scope->tribeIds,
                'clan_ids' => $scope->clanIds,
                'branch_ids' => $scope->branchIds,
            ],
            'permissions' => $scope->permissions,
        ];
    }
}
