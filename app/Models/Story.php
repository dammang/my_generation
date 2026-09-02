<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A family narrative.
 */
class Story extends Model
{
    use Contributable, HasFactory, HasPrivacyLevel, HasUlid, HasVerificationStatus, SoftDeletesWithUniqueness;

    protected string $privacyColumn = 'visibility';

    protected $table = 'stories';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'body',
        'summary',
        'person_id',
        'family_branch_id',
        'clan_id',
        'tribe_id',
        'author_id',
        'language',
        'story_type',
        'era_start_year',
        'era_end_year',
        'visibility',
        'verification_status',
        'view_count',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'story_type' => StoryType::class,
            'visibility' => PrivacyLevel::class,
            'verification_status' => VerificationStatus::class,
            'era_start_year' => 'integer',
            'era_end_year' => 'integer',
            'view_count' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
