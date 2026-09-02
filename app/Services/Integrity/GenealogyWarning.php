<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use JsonSerializable;

/**
 * A doubt recorded alongside a successful write.
 *
 * Warnings are the deliberate alternative to hard validation. Historical
 * records are wrong, ambiguous and sometimes genuinely strange; refusing a
 * contributor because a nineteenth-century church register disagrees with
 * itself loses the data forever. Flagging it preserves the data *and* the doubt.
 */
final readonly class GenealogyWarning implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $field = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_filter([
            'code' => $this->code,
            'message' => $this->message,
            'field' => $this->field,
        ], fn ($value) => $value !== null);
    }
}
