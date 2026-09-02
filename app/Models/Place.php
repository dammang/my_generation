<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A node in the reusable gazetteer: country → state → district → township → village.
 * Depth is data, not schema — `type` is a string because jurisdictions differ.
 */
class Place extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'places';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'path',
        'depth',
        'name',
        'native_name',
        'type',
        'country_code',
        'latitude',
        'longitude',
        'historical_names',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'historical_names' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'depth' => 'integer',
        ];
    }
}
