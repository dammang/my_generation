<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Processing state of an uploaded file.
 */
enum MediaStatus: string
{
    use HasLabel;

    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
