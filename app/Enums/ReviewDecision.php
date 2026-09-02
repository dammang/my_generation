<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * A single reviewer's decision on a change request.
 */
enum ReviewDecision: string
{
    use HasLabel;

    case Approve = 'approve';
    case Reject = 'reject';
    case RequestInfo = 'request_info';
    case Dispute = 'dispute';
}
