<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Push notification target platform.
 */
enum DevicePlatform: string
{
    use HasLabel;

    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
}
