<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lookup, not an enum: tribes have culturally specific events and adding one
 * must never require a migration. tribe_id NULL means system-wide.
 */
class EventType extends Model
{
    use HasFactory;

    protected $table = 'event_types';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'label',
        'category',
        'tribe_id',
        'is_system',
        'icon',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tribe(): BelongsTo
    {
        return $this->belongsTo(Tribe::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PersonEvent::class);
    }
}
