<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\Gender;
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
 * The person node.
 *
 * A user account is NOT a person: a deceased grandfather exists here with no
 * account, and a user is linked only through an approved profile claim.
 */
class Person extends Model
{
    use Contributable, HasFactory, HasPrivacyLevel, HasUlid, HasUncertainDates, HasVerificationStatus, SoftDeletesWithUniqueness;

    /** @var array<int, string> */
    protected array $uncertainDates = ['birth', 'death'];

    protected $table = 'people';

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'native_name',
        'nickname',
        'display_name',
        'sort_name',
        'gender',
        'birth_date',
        'birth_date_end',
        'birth_date_precision',
        'birth_date_text',
        'birth_year',
        'birth_place_id',
        'death_date',
        'death_date_end',
        'death_date_precision',
        'death_date_text',
        'death_year',
        'death_place_id',
        'burial_place_id',
        'is_living',
        'living_reviewed_at',
        'biography',
        'profile_media_id',
        'cover_media_id',
        'tribe_id',
        'clan_id',
        'family_branch_id',
        'generation_id',
        'privacy_level',
        'verification_status',
        'has_open_dispute',
        'merged_into_person_id',
        'external_ref',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'birth_date_end' => 'date',
            'death_date' => 'date',
            'death_date_end' => 'date',
            'birth_date_precision' => DatePrecision::class,
            'death_date_precision' => DatePrecision::class,
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_living' => 'boolean',
            'has_open_dispute' => 'boolean',
            'privacy_level' => PrivacyLevel::class,
            'verification_status' => VerificationStatus::class,
            'living_reviewed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Deceased only when the record proves it. A person with no dates at all is
     * treated as living, and gets the strictest privacy handling — fail closed.
     */
    public function isDeceased(): bool
    {
        if ($this->death_date !== null || $this->death_year !== null) {
            return true;
        }

        $maxAge = (int) config('genealogy.living.max_age');

        return $this->birth_year !== null
            && $this->birth_year < (int) date('Y') - $maxAge;
    }

    public function isMinor(): bool
    {
        if ($this->isDeceased() || $this->birth_year === null) {
            return false;
        }

        return $this->birth_year > (int) date('Y') - (int) config('genealogy.living.minor_age');
    }

    /** A merged-away record: kept so old ULIDs and share links still resolve. */
    public function isTombstone(): bool
    {
        return $this->merged_into_person_id !== null;
    }

    /** Lifespan as shown on a tree card, e.g. "1920–1998" or "b. 1975". */
    public function lifespan(): ?string
    {
        $birth = $this->birth_year;
        $death = $this->death_year;

        return match (true) {
            $birth !== null && $death !== null => "{$birth}–{$death}",
            $birth !== null => "b. {$birth}",
            $death !== null => "d. {$death}",
            default => null,
        };
    }
}
