<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Somebody has asked to join a tribe, clan or branch you administer.
 *
 * Database only, unlike ChangeRequestAwaitingReview: there is no screen in the
 * app yet for reviewing pending members, only the admin panel. A push whose
 * tap lands nowhere is worse than no push — add FcmChannel once that screen
 * exists, alongside a deep link that route can actually answer.
 */
class MembershipRequestAwaitingReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Membership $membership) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->membership->loadMissing(['user:id,ulid,name', 'scope.scopeable']);

        return [
            'type' => 'membership.awaiting_review',
            'membership_ulid' => $this->membership->ulid,
            'requested_by' => $this->membership->user?->name,
            'scope' => $this->membership->scope?->scopeable?->name,
        ];
    }
}
