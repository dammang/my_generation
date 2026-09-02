<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Contributor confidence in an asserted fact.
 */
enum Certainty: string
{
    use HasLabel;

    case Proven = 'proven';
    case Probable = 'probable';
    case Possible = 'possible';
    case Disputed = 'disputed';
}
