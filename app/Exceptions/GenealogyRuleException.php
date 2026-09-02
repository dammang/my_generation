<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A genealogical rule that is a hard error rather than a warning.
 *
 * The bar is deliberately high: historical records are wrong, ambiguous and
 * sometimes genuinely strange, and blocking a contributor loses the data
 * forever. Only violations that make downstream traversal *incorrect* — rather
 * than merely doubtful — belong here. Everything else is a warning returned
 * alongside a successful write.
 */
class GenealogyRuleException extends ApiException
{
    /**
     * Not named $code: Exception already declares a non-readonly $code, and a
     * promoted readonly property of the same name is a fatal redeclaration.
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'GENEALOGY_RULE_VIOLATED',
        array $errors = [],
    ) {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
