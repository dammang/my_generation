<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A human label for a generation. Nothing in the traversal engine depends on it:
 * a missing or wrong number degrades a caption, never the tree.
 */
class Generation extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'generations';

    /** @var list<string> */
    protected $fillable = [
        'tribe_id',
        'clan_id',
        'generation_number',
        'generation_name',
        'local_name',
        'description',
        'estimated_start_year',
        'estimated_end_year',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'generation_number' => 'integer',
            'estimated_start_year' => 'integer',
            'estimated_end_year' => 'integer',
        ];
    }
}
