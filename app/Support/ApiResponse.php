<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/**
 * The single place an API response envelope is built.
 *
 * Every endpoint returns the same shape, so the Flutter client has one parser
 * and one error path rather than a per-endpoint guess:
 *
 *   { "success": true,  "data": …, "meta": {…}, "warnings": [] }
 *   { "success": false, "message": "…", "errors": {…}, "code": "…" }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $warnings
     */
    public static function success(
        mixed $data = null,
        array $meta = [],
        array $warnings = [],
        int $status = 200,
    ): JsonResponse {
        $payload = ['success' => true];

        if ($data instanceof ResourceCollection || $data instanceof AbstractPaginator) {
            [$items, $paginationMeta] = self::unwrapPagination($data);
            $payload['data'] = $items;
            $meta = [...$paginationMeta, ...$meta];
        } elseif ($data instanceof JsonResource) {
            $payload['data'] = $data->resolve();
        } else {
            $payload['data'] = $data;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        // Always present, so clients can read response.warnings without a
        // null check. Genealogy writes routinely succeed *and* carry doubt.
        $payload['warnings'] = $warnings;

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, array $meta = [], array $warnings = []): JsonResponse
    {
        return self::success($data, $meta, $warnings, 201);
    }

    /** A write that became a change request rather than a direct edit. */
    public static function accepted(mixed $data = null, array $meta = [], array $warnings = []): JsonResponse
    {
        return self::success($data, $meta, $warnings, 202);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $meta  Structured detail the client needs to
     *                                      recover — the unions it must choose
     *                                      between, the duplicate it may have
     *                                      meant. Without this a client has to
     *                                      parse ids back out of a sentence.
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        ?string $code = null,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
            'code' => $code ?? self::codeForStatus($status),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Cursor pagination throughout. Offset pagination over a million rows with
     * a privacy predicate degrades badly at high offsets, and genealogy lists
     * are "keep scrolling", not "jump to page 400".
     *
     * @return array{0: mixed, 1: array<string, mixed>}
     */
    private static function unwrapPagination(mixed $data): array
    {
        $response = $data instanceof ResourceCollection
            ? $data->response()->getData(true)
            : $data->toArray();

        $items = $response['data'] ?? [];

        $meta = array_filter([
            'per_page' => $response['per_page'] ?? ($response['meta']['per_page'] ?? null),
            'cursor' => $response['meta']['cursor'] ?? null,
            'next_cursor' => $response['next_cursor'] ?? ($response['meta']['next_cursor'] ?? null),
            'prev_cursor' => $response['prev_cursor'] ?? ($response['meta']['prev_cursor'] ?? null),
        ], fn ($value) => $value !== null);

        $meta['has_more'] = ($meta['next_cursor'] ?? null) !== null;

        return [$items, $meta];
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'AUTHORIZATION_FAILED',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            429 => 'TOO_MANY_REQUESTS',
            default => $status >= 500 ? 'SERVER_ERROR' : 'ERROR',
        };
    }
}
