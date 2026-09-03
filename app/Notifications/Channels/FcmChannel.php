<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * Delivers a notification to every device an account has registered.
 *
 * A push is a convenience, never the record. The database notification is
 * written first and independently, so somebody whose phone was off, or who
 * revoked notification permission, still finds the thing waiting in the app.
 * A push that fails must not lose the message.
 */
class FcmChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $messaging = $this->messaging();

        // No Firebase configured — a deployment that has not set it up yet, or
        // a test run. The database notification has already been written, so
        // there is nothing to fail about: push is the convenience, not the
        // record. Injecting Messaging into the constructor made a missing
        // credential break the write that triggered the notification.
        if ($messaging === null) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $notifiable->getKey())
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        /** @var array{title: string, body: string, data?: array<string, string>} $payload */
        $payload = $notification->toFcm($notifiable);

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($payload['title'], $payload['body']))
            // Data keys must be strings for FCM; a nested array is silently
            // dropped, which shows up as a notification that opens nothing.
            ->withData(array_map('strval', $payload['data'] ?? []));

        try {
            $report = $messaging->sendMulticast($message, $tokens);

            $this->forgetDeadTokens($report->invalidTokens(), $report->unknownTokens());
        } catch (MessagingException $e) {
            // Never rethrow: the write that triggered this already succeeded,
            // and failing the request because a push did not go out would undo
            // somebody's contribution over a notification.
            Log::warning('FCM delivery failed', ['reason' => $e->getMessage()]);
        }
    }

    /** Null when Firebase is not configured for this deployment. */
    private function messaging(): ?Messaging
    {
        if (blank(config('firebase.projects.app.credentials'))) {
            return null;
        }

        try {
            return app(Messaging::class);
        } catch (Throwable $e) {
            Log::warning('Firebase messaging unavailable', ['reason' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * A registration is dead once the app is uninstalled or the token rotates.
     * Left in place they accumulate for the life of the account and every send
     * pays for them.
     *
     * @param  array<int, string>  $invalid
     * @param  array<int, string>  $unknown
     */
    private function forgetDeadTokens(array $invalid, array $unknown): void
    {
        $dead = array_merge($invalid, $unknown);

        if ($dead !== []) {
            DeviceToken::whereIn('token', $dead)->delete();
        }
    }
}
