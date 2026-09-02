<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * State of a disagreement over a fact.
 */
enum DisputeStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Resolved = 'resolved';
    case Withdrawn = 'withdrawn';
}
