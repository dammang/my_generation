<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonNameType;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An alternate, native, historical or translated spelling.
 * This is why "Thawng Dam", "Thawngdam" and "Thawng Dham" find the same ancestor.
 */
class PersonName extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'person_names';

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
}
