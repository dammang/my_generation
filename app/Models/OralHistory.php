<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\TranscriptStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Model;

/**
 * A recorded interview with an elder. Transcription is not implemented in v1;
 * the columns exist so enabling it later is a job, not a migration on a table
 * that by then holds thousands of recordings.
 */
class OralHistory extends Model
{
    use Contributable, HasPrivacyLevel, HasUlid, HasVerificationStatus, SoftDeletesWithUniqueness;

    protected string $privacyColumn = 'visibility';

    protected $table = 'oral_histories';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'media_id',
        'interviewee_person_id',
        'interviewer_person_id',
        'interviewer_user_id',
        'recorded_at',
        'place_id',
        'language',
        'transcript_status',
        'transcript_text',
        'translation_language',
        'translation_text',
        'duration_seconds',
        'visibility',
        'verification_status',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transcript_status' => TranscriptStatus::class,
            'visibility' => PrivacyLevel::class,
            'verification_status' => VerificationStatus::class,
            'recorded_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }
}
