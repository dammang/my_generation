<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonNameType;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\RecordsRevisions;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An alternate, native, historical or translated spelling.
 * This is why "Thawng Dam", "Thawngdam" and "Thawng Dham" find the same ancestor.
 */
class PersonName extends Model
{
    use HasFactory, HasUlid, RecordsRevisions, SoftDeletesWithUniqueness;

    protected $table = 'person_names';

    /**
     * Fields whose every change is written to the revision ledger.
     * Counters, derived years and cache flags are deliberately absent —
     * they are not genealogical claims and would bury the real history.
     *
     * @var array<int, string>
     */
    protected array $revisionable = [
        'name',
        'type',
        'script',
        'language',
        'is_primary',
    ];

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'name',
        'normalized',
        'phonetic',
        'type',
        'script',
        'language',
        'is_primary',
        'source_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PersonNameType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
