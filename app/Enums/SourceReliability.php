<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How much weight a source carries when resolving a dispute.
 */
enum SourceReliability: string
{
    use HasLabel;

    case Primary = 'primary';
    case Secondary = 'secondary';
    case Questionable = 'questionable';
    case Unreliable = 'unreliable';
}
