<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How far an oral history recording has been transcribed.
 */
enum TranscriptStatus: string
{
    use HasLabel;

    case None = 'none';
    case Pending = 'pending';
    case Machine = 'machine';
    case HumanReviewed = 'human_reviewed';
}
