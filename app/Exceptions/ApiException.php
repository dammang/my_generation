<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A domain failure that already knows how it should reach the client.
 *
 * Rendering lives on the exception so a new failure mode cannot be added
 * without deciding its status code and machine-readable code.
 */
abstract class ApiException extends RuntimeException
{
    /** @var array<string, array<int, string>> */
    protected array $errors = [];

    abstract public function status(): int;

    abstract public function errorCode(): string;

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status(), $this->errors(), $this->errorCode());
    }
}
