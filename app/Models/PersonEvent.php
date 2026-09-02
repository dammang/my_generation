<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\HasUncertainDates;
use App\Models\Concerns\HasVerificationStatus;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A timeline entry. Migration is modelled here with from_place_id/to_place_id
 * rather than in a separate table — a person's migrations belong on their timeline.
 */
class PersonEvent extends Model
{
    use Contributable, HasFactory, HasPrivacyLevel, HasUlid, HasUncertainDates, HasVerificationStatus, SoftDeletesWithUniqueness;

    /** @var array<int, string> */
    protected array $uncertainDates = ['event'];

    protected $table = 'person_events';

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'event_type_id',
        'union_id',
        'title',
        'description',
        'event_date',
        'event_date_end',
        'event_date_precision',
        'event_date_text',
        'event_year',
        'place_id',
        'from_place_id',
        'to_place_id',
        'privacy_level',
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
            'event_date' => 'date',
            'event_date_end' => 'date',
            'event_date_precision' => DatePrecision::class,
            'event_year' => 'integer',
            'privacy_level' => PrivacyLevel::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }
}
