<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a dispute was settled. 'both_recorded' is a legitimate outcome.
 */
enum DisputeResolution: string
{
    use HasLabel;

    case ClaimAccepted = 'claim_accepted';
    case BothRecorded = 'both_recorded';
    case InsufficientEvidence = 'insufficient_evidence';
    case Withdrawn = 'withdrawn';
}
