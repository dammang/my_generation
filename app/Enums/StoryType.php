<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Kind of family narrative.
 */
enum StoryType: string
{
    use HasLabel;

    case Narrative = 'narrative';
    case Memory = 'memory';
    case Tradition = 'tradition';
    case Migration = 'migration';
    case Historical = 'historical';
    case Biography = 'biography';
    case Other = 'other';
}
