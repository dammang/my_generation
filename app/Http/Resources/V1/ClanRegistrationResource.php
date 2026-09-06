<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ClanRegistration;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClanRegistration */
class ClanRegistrationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status->value,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'description' => $this->description,
            'statement' => $this->statement,
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'tribe' => $this->whenLoaded('tribe', fn () => [
                'ulid' => $this->tribe->ulid,
                'name' => $this->tribe->name,
            ]),

            'parent_clan' => $this->whenLoaded('parentClan', fn () => $this->parentClan === null ? null : [
                'ulid' => $this->parentClan->ulid,
                'name' => $this->parentClan->name,
            ]),

            // Where the requester says the clan begins, masked like any other
            // person: asking to start a clan does not entitle a reviewer to see
            // somebody they otherwise could not.
            'ancestor' => $this->whenLoaded('ancestor', fn () => $this->ancestor === null
                ? null
                : PersonResource::make($this->ancestor)),

            'requester' => $this->whenLoaded('requester', fn () => [
                'ulid' => $this->requester->ulid,
                'name' => $this->requester->name,
            ]),

            // Null until approved. What the request became, so a client does
            // not have to go looking for it.
            'clan' => $this->whenLoaded('clan', fn () => $this->clan === null ? null : [
                'ulid' => $this->clan->ulid,
                'name' => $this->clan->name,
            ]),

            // What the reader may do with it, answered here rather than left
            // for a client to infer from permissions it does not fully model.
            // The two are mutually exclusive by design: nobody decides their
            // own request, and nobody withdraws somebody else's.
            'can_decide' => $this->canDecide($request->user()),
            'can_withdraw' => $this->isPending() && $request->user()?->is($this->requester) === true,
        ];
    }

    /**
     * Mirrors DecideClanRegistration::assertMayDecide.
     *
     * A near-copy of a rule is a liability, but the alternative is a screen
     * that offers an Approve button and then refuses it — and the action's
     * check throws rather than returning, so it cannot be asked politely.
     */
    private function canDecide(?User $user): bool
    {
        if ($user === null || ! $this->isPending() || $user->is($this->requester)) {
            return false;
        }

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe_id)
            ->first();

        return $scope !== null
            && app(PermissionResolver::class)->can($user, 'clans.manage', $scope->path);
    }
}
