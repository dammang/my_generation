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
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A family narrative.
 */
class Story extends Model
{
    use Contributable, HasFactory, HasPrivacyLevel, HasUlid, HasVerificationStatus, RecordsRevisions, SoftDeletesWithUniqueness;

    protected string $privacyColumn = 'visibility';

    protected $table = 'stories';

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'title',
        'body',
        'summary',
        'visibility',
        'verification_status',
        'story_type',
        'era_start_year',
        'era_end_year',
    ];

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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function familyBranch(): BelongsTo
    {
        return $this->belongsTo(FamilyBranch::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'story_people')->withPivot('role');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
