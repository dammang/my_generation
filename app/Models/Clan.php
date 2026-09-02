<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Clan → sub-clan → branch, to arbitrary depth. `depth` records how deep this row
 * sits and `level_label` carries the tribe's own word for that level, because no
 * fixed number of hierarchy levels is assumed.
 */
class Clan extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'clans';

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'parent_clan_id',
        'path',
        'depth',
        'level_label',
        'name',
        'slug',
        'native_name',
        'description',
        'history',
        'logo_media_id',
        'cover_media_id',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'depth' => 'integer',
        ];
    }
}
