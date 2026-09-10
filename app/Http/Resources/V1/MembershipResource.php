<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Membership;
use App\Services\Media\MediaUrlResolver;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** @mixin Membership */
class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'scope' => $this->whenLoaded('scope', fn () => [
                'type' => $this->scope->scopeable_type,
                'ulid' => $this->scope->scopeable?->ulid,
                'name' => $this->scope->scopeable?->name,
            ]),
            // Only ever exposed to somebody who administers the scope.
            'user' => $this->whenLoaded('user', fn () => [
                'ulid' => $this->user->ulid,
                'name' => $this->user->name,
            ]),

            // What the applicant said about themselves. Shown to whoever is
            // deciding, and to the applicant looking at their own request —
            // to nobody else, because it is a stranger's parents, their
            // country and a photograph of their face.
            ...$this->when(
                $this->mayReadAnswers($request),
                fn () => $this->answers(),
                [],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * Omitted entirely when nothing was asked — a tribe asks nothing, so its
     * memberships have no answers. An empty PHP array serialises as a JSON
     * *array*, not an object, and a client reading it as one crashed on every
     * membership that had no answers: the whole list failed to load because
     * one row had nothing to say.
     */
    private function answers(): array
    {
        $answers = array_filter([
            'name' => $this->applicant_name,
            // Beside the answers rather than beside the account, because it is
            // guarded by the same rule: a reviewer needs a way to reach
            // somebody, and nobody else needs their address.
            'email' => $this->relationLoaded('user') ? $this->user?->email : null,
            'father' => $this->father_name,
            'mother' => $this->mother_name,
            'grandfather' => $this->grandfather_name,
            'grandmother' => $this->grandmother_name,
            'country' => $this->country,
            'contact' => $this->contact,
            'photo_url' => $this->selfieUrl(),
        ], fn ($value) => $value !== null);

        return $answers === [] ? [] : ['applicant' => $answers];
    }

    /**
     * The applicant themselves, or somebody who administers the scope they
     * asked to join.
     */
    private function mayReadAnswers(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->getKey() === $this->user_id) {
            return true;
        }

        return $this->relationLoaded('scope')
            && $this->scope !== null
            && app(PermissionResolver::class)
                ->administersMembership($user, $this->scope->path);
    }

    /**
     * Signed and short-lived, like any other private object. Null where the
     * disk cannot sign, which renders as no photograph rather than handing out
     * a permanent link to somebody's face.
     */
    private function selfieUrl(): ?string
    {
        if (blank($this->photo_path)) {
            return null;
        }

        $disk = config('filesystems.disks.r2') !== null ? 'r2' : 'local';

        try {
            return Storage::disk($disk)->temporaryUrl(
                $this->photo_path,
                now()->addMinutes(MediaUrlResolver::SIGNED_URL_MINUTES),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
