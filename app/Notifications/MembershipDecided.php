<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant what happened to the request they made.
 *
 * database and push both, unlike MembershipRequestAwaitingReview: the home
 * screen already has a place for this to land — the "Waiting for approval"
 * card reads the same membership list, and the row it names disappears from
 * there the moment this fires. A reviewer notification had nowhere to send
 * somebody; this one does.
 */
class MembershipDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Membership $membership) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->membership->loadMissing('scope.scopeable');

        return [
            'type' => 'membership.decided',
            'membership_ulid' => $this->membership->ulid,
            'status' => $this->membership->status->value,
            'scope' => $this->scopeName(),
        ];
    }

    /** @return array{title: string, body: string, data: array<string, string>} */
    public function toFcm(object $notifiable): array
    {
        $scope = $this->scopeName();

        return match ($this->membership->status) {
            MembershipStatus::Active => [
                'title' => 'Request approved',
                'body' => "You're now a member of {$scope}.",
                'data' => ['route' => '/home'],
            ],
            // Rejected is the only other outcome DecideMembership can record —
            // Pending and Left never reach this notification.
            default => [
                'title' => 'Request not approved',
                'body' => "Your request to join {$scope} was not approved.",
                'data' => ['route' => '/home'],
            ],
        };
    }

    private function scopeName(): string
    {
        $this->membership->loadMissing('scope.scopeable');

        return $this->membership->scope?->scopeable?->name ?? 'the tribe';
    }
}
