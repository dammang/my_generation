<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChangeRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Somebody has suggested a correction you can decide on.
 *
 * Queued: a contributor pressing save should not wait on Google's servers to
 * accept a push before their own write is acknowledged.
 */
class ChangeRequestAwaitingReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ChangeRequest $changeRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Database first and always. The push is the convenience; the record in
        // the app is what somebody comes back to when their phone was off.
        return ['database', FcmChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'change_request.awaiting_review',
            'change_request_ulid' => $this->changeRequest->ulid,
            'target' => $this->subject(),
            'requested_by' => $this->changeRequest->requester?->name,
        ];
    }

    /** @return array{title: string, body: string, data: array<string, string>} */
    public function toFcm(object $notifiable): array
    {
        $who = $this->changeRequest->requester?->name ?? 'Somebody';

        return [
            'title' => 'A correction to review',
            'body' => "{$who} suggested a change to {$this->subject()}.",
            'data' => [
                // Deep link, so tapping the notification lands on the thing it
                // is about rather than on the home screen.
                'route' => '/contributions?tab=review',
                'change_request_ulid' => $this->changeRequest->ulid,
            ],
        ];
    }

    private function subject(): string
    {
        $target = $this->changeRequest->target;

        return $target?->display_name ?? $target?->title ?? 'a record';
    }
}
