<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A named family line. `ancestor_person_id` is the apical ancestor this branch
 * counts generations from — the root used by lineage_depths.
 */
class FamilyBranch extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'family_branches';

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'clan_id',
        'ancestor_person_id',
        'name',
        'slug',
        'native_name',
        'description',
        'origin_place_id',
        'current_place_id',
        'current_region',
        'cover_media_id',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }
}
