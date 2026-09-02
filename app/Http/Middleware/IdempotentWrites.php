<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SyncStatus;
use App\Models\SyncOperation;
use App\Services\Sync\IdempotencyLedger;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a retried write harmless.
 *
 * A phone that loses its acknowledgement cannot tell a failed request from a
 * successful one it never heard about, so it retries. Without a ledger that
 * retry creates a second grandfather — and in a genealogy the duplicate then
 * has to be found and merged by hand.
 *
 * The client supplies an operation id; the ledger is keyed on it. Claiming the
 * key *before* doing the work makes the unique index the lock, so two identical
 * requests racing each other cannot both execute.
 */
class IdempotentWrites
{
    /** Methods that change something. A GET needs no protection. */
    private const GUARDED = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly IdempotencyLedger $ledger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->keyFrom($request);
        $user = $request->user();

        if ($key === null || $user === null || ! in_array($request->method(), self::GUARDED, true)) {
            return $next($request);
        }

        $endpoint = $request->method().' '.$request->path();
        $hash = $this->ledger->hash($endpoint, $request->except(['client_operation_id']));

        $seen = $this->ledger->claim($user, $key, $endpoint, $hash);

        if ($seen !== null) {
            return $this->replay($seen, $hash);
        }

        $response = $next($request);

        $this->ledger->settle($user, $key, $response->getStatusCode(), $this->bodyOf($response));

        return $response;
    }

    /** @return array<string, mixed>|null */
    private function bodyOf(Response $response): ?array
    {
        $decoded = json_decode($response->getContent() ?: 'null', true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Answers a repeat of an operation already seen.
     *
     * A different payload under the same key is a client bug, not a retry.
     * Replaying the first response would tell them their second, different
     * change succeeded when nothing happened.
     */
    private function replay(SyncOperation $operation, string $hash): Response
    {
        if ($operation->request_hash !== $hash) {
            return ApiResponse::error(
                'This operation id was already used for a different change.',
                409,
                code: 'IDEMPOTENCY_KEY_REUSED',
            );
        }

        if ($operation->status === SyncStatus::Pending) {
            return ApiResponse::error(
                'That change is still being applied. Try again in a moment.',
                409,
                code: 'OPERATION_IN_FLIGHT',
            );
        }

        return response()->json(
            $operation->response_body ?? ['success' => true],
            $operation->response_code ?? 200,
            // Says plainly that nothing ran this time, so a client is never
            // left guessing whether its retry did something.
            ['Idempotent-Replay' => 'true'],
        );
    }

    /**
     * The header is preferred: it applies to every endpoint uniformly, whereas
     * a body field only exists where a form request declares one.
     */
    private function keyFrom(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key')
            ?? $request->input('client_operation_id');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1 ? $key : null;
    }
}
