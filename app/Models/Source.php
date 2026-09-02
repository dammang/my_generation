<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\SourceReliability;
use App\Enums\SourceType;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A document, record or testimony backing a genealogical fact.
 */
class Source extends Model
{
    use HasFactory, HasPrivacyLevel, HasUlid, RecordsRevisions, SoftDeletesWithUniqueness;

    protected $table = 'sources';

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
        'source_type' => SourceType::Other->value,
        'reliability' => SourceReliability::Secondary->value,
        'privacy_level' => PrivacyLevel::Tribe->value,
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
        'source_type',
        'description',
        'author',
        'publication_year',
        'url',
        'reliability',
        'privacy_level',
    ];

    /** @var list<string> */
    protected $fillable = [
        'title',
        'source_type',
        'description',
        'author',
        'publisher',
        'publication_year',
        'repository',
        'url',
        'media_id',
        'informant_person_id',
        'reliability',
        'tribe_id',
        'clan_id',
        'privacy_level',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'reliability' => SourceReliability::class,
            'privacy_level' => PrivacyLevel::class,
            'publication_year' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Who gave the testimony, for oral sources. */
    public function informant(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'informant_person_id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }
}
