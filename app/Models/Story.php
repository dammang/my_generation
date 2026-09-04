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
use App\Services\Privacy\ViewerScope;
use Illuminate\Database\Eloquent\Builder;
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
     * In-memory defaults mirroring the column defaults.
     *
     * A model created without these reports null for columns the database
     * would have filled, until it is reloaded — which surfaces as a fatal
     * error the moment a resource reads ->value on a null enum.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'story_type' => StoryType::Narrative->value,
        'visibility' => PrivacyLevel::Family->value,
        'verification_status' => VerificationStatus::Unverified->value,
        'view_count' => 0,
    ];

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

    /**
     * Stories a viewer is entitled to read.
     *
     * StoryPolicy::view() answers true for everybody — deliberately, because
     * the policy was written before there was any way to reach a story at
     * all. Now that there is, the entitlement has to live somewhere real, and
     * a query scope is the only place it cannot be forgotten at a call site.
     *
     * Authority flows downward the same way it does in PermissionResolver: an
     * administrator of a tribe can read a family's story inside it, because
     * they can already read the family. It does not flow upward or sideways —
     * belonging to one clan tells you nothing about another.
     */
    public function scopeVisibleTo(Builder $query, ViewerScope $viewer): Builder
    {
        if ($viewer->isSuperAdmin) {
            return $query;
        }

        $tribeIds = [...$viewer->tribeIds, ...$viewer->adminTribeIds];
        $clanIds = [...$viewer->clanIds, ...$viewer->adminClanIds];
        $branchIds = [...$viewer->branchIds, ...$viewer->adminBranchIds];

        return $query->where(function (Builder $query) use ($viewer, $tribeIds, $clanIds, $branchIds): void {
            $query->where('visibility', PrivacyLevel::Public);

            // Your own account's story, whatever level you filed it at —
            // including Private, which is what Private means.
            if ($viewer->userId !== null) {
                $query->orWhere('author_id', $viewer->userId);
            }

            if ($tribeIds !== []) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('visibility', PrivacyLevel::Tribe)
                    ->whereIn('tribe_id', $tribeIds));
            }

            // A clan story also reaches an administrator of the tribe above it.
            if ($clanIds !== [] || $viewer->adminTribeIds !== []) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('visibility', PrivacyLevel::Clan)
                    ->where(fn (Builder $inner) => $inner
                        ->whereIn('clan_id', $clanIds)
                        ->orWhereIn('tribe_id', $viewer->adminTribeIds)));
            }

            if ($branchIds !== [] || $clanIds !== [] || $viewer->adminTribeIds !== []) {
                $query->orWhere(fn (Builder $q) => $q
                    ->where('visibility', PrivacyLevel::Family)
                    ->where(fn (Builder $inner) => $inner
                        ->whereIn('family_branch_id', $branchIds)
                        ->orWhereIn('clan_id', $viewer->adminClanIds)
                        ->orWhereIn('tribe_id', $viewer->adminTribeIds)));
            }
        });
    }

    /**
     * The same rule as scopeVisibleTo, for one already-loaded record — so the
     * policy and the listing cannot drift into disagreeing.
     */
    public function isVisibleTo(ViewerScope $viewer): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleTo($viewer)
            ->exists();
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
