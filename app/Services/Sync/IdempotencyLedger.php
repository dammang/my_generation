<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\SyncStatus;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Remembers what a client's operation id already did.
 *
 * A phone that loses its acknowledgement cannot tell a failed write from a
 * successful one it never heard about, so it retries. Without this, the retry
 * creates a second grandfather — and a duplicate in a genealogy then has to be
 * found and merged by hand.
 *
 * Claiming the key *before* the work runs makes the unique index the lock, so
 * two identical requests racing each other cannot both execute.
 */
class IdempotencyLedger
{
    /**
     * How long a claim may stay unfinished before it is treated as abandoned.
     *
     * A process killed between claiming the key and recording the outcome would
     * otherwise leave that operation id pending forever, and the client would
     * be told "still being applied" every time it retried — for good.
     */
    private const STALE_AFTER_MINUTES = 10;

    /**
     * Claims an operation id.
     *
     * Returns the existing row when this id has been seen — the caller replays
     * it rather than working again — and null when the claim succeeded and the
     * work should proceed.
     */
    public function claim(User $user, string $key, string $endpoint, string $hash): ?SyncOperation
    {
        $existing = $this->find($user, $key);

        if ($existing !== null) {
            if (! $this->isAbandoned($existing)) {
                return $existing;
            }

            // Nobody is coming back to finish this one.
            $existing->delete();
        }

        try {
            SyncOperation::create([
                'user_id' => $user->getKey(),
                'client_operation_id' => $key,
                'endpoint' => substr($endpoint, 0, 120),
                'request_hash' => $hash,
                'status' => SyncStatus::Pending,
                'response_code' => null,
            ]);

            return null;
        } catch (QueryException) {
            // Lost the race. Whoever won is either done or still working;
            // either way this caller must not also execute.
            return $this->find($user, $key);
        }
    }

    private function isAbandoned(SyncOperation $operation): bool
    {
        return $operation->status === SyncStatus::Pending
            && $operation->created_at?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES)) === true;
    }

    /**
     * Drops entries old enough that no client would still be retrying them.
     *
     * The ledger records every write by every account, so without this it grows
     * for the life of the application.
     */
    public function prune(int $days = 30): int
    {
        return SyncOperation::where('created_at', '<', now()->subDays($days))->delete();
    }

    public function find(User $user, string $key): ?SyncOperation
    {
        return SyncOperation::where('user_id', $user->getKey())
            ->where('client_operation_id', $key)
            ->first();
    }

    /**
     * Records the outcome against a claimed key.
     *
     * A server error is deliberately not recorded: the client should be free to
     * retry the same operation id and have it actually run.
     *
     * @param  array<string, mixed>|null  $body
     */
    public function settle(User $user, string $key, int $status, ?array $body): void
    {
        $operation = $this->find($user, $key);

        if ($operation === null) {
            return;
        }

        if ($status >= 500) {
            $operation->delete();

            return;
        }

        $operation->forceFill([
            'status' => $status < 400 ? SyncStatus::Applied : SyncStatus::Rejected,
            'response_code' => $status,
            'response_body' => $body,
        ])->save();
    }

    /** Releases a claim so the operation can be attempted again. */
    public function release(User $user, string $key): void
    {
        $this->find($user, $key)?->delete();
    }

    /** @param  array<string, mixed>  $payload */
    public function hash(string $endpoint, array $payload): string
    {
        return hash('sha256', json_encode([$endpoint, $payload]));
    }
}
