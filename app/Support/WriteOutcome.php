<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChangeRequest;
use App\Services\Integrity\GenealogyWarning;
use Illuminate\Database\Eloquent\Model;

/**
 * What a genealogy write actually produced.
 *
 * A write can succeed outright, or become a proposal for somebody with verify
 * permission to review. Both are successful outcomes — the second is the point
 * of a collaborative archive, not a consolation prize — so the shape carries
 * either, plus whatever doubts were recorded along the way.
 */
final readonly class WriteOutcome
{
    /** @param  array<int, GenealogyWarning>  $warnings */
    public function __construct(
        public ?Model $record = null,
        public ?ChangeRequest $changeRequest = null,
        public array $warnings = [],
        /** @var array<string, int> */
        public array $created = [],
    ) {}

    public function wasProposed(): bool
    {
        return $this->changeRequest !== null;
    }

    /** 201 for a direct write, 202 when it became a proposal. */
    public function status(): int
    {
        return $this->wasProposed() ? 202 : 201;
    }

    /** @return array<int, array<string, mixed>> */
    public function warningPayload(): array
    {
        return array_map(fn (GenealogyWarning $w) => $w->jsonSerialize(), $this->warnings);
    }
}
