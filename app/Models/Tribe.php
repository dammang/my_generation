<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use App\Models\Concerns\HasPrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The top-level heritage group. `graph_version` is bumped on any genealogy write
 * within the tribe and forms part of every tree cache key, so invalidation is O(1).
 */
class Tribe extends Model
{
    use HasFactory, HasPrivacyLevel, HasUlid, SoftDeletesWithUniqueness;

    protected string $privacyColumn = 'default_privacy_level';

    protected $table = 'tribes';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'native_name',
        'short_name',
        'description',
        'history',
        'logo_media_id',
        'cover_media_id',
        'country_code',
        'region',
        'primary_place_id',
        'default_privacy_level',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_privacy_level' => PrivacyLevel::class,
            'status' => RecordStatus::class,
        ];
    }
}
