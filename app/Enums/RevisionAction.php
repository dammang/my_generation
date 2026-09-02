<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What kind of change a revision row records.
 */
enum RevisionAction: string
{
    use HasLabel;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Merged = 'merged';
    case Verified = 'verified';
    case Disputed = 'disputed';
}
