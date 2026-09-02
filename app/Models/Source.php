<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\SourceReliability;
use App\Enums\SourceType;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A document, record or testimony backing a genealogical fact.
 */
class Source extends Model
{
    use HasFactory, HasPrivacyLevel, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'sources';

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
}
