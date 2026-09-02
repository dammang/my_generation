<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use App\Services\Privacy\ViewerScope;
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
        $scope = app(ViewerScope::class);

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
            'person' => $this->whenLoaded('person', fn () => PersonResource::make($this->person)),

            'scopes' => [
                'tribe_ids' => $scope->tribeIds,
                'clan_ids' => $scope->clanIds,
                'branch_ids' => $scope->branchIds,
            ],
            'permissions' => $scope->permissions,
        ];
    }
}
