<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a change request proposes to do.
 */
enum ChangeRequestOperation: string
{
    use HasLabel;

    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Link = 'link';
    case Unlink = 'unlink';
    case Merge = 'merge';
}
