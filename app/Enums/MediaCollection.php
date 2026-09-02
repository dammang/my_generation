<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What role a file plays on its owning record.
 */
enum MediaCollection: string
{
    use HasLabel;

    case Profile = 'profile';
    case Cover = 'cover';
    case Gallery = 'gallery';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
    case Logo = 'logo';
}
