<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Certainty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links a source to a fact — and, crucially, to a specific field. Field-level
 * citation is what lets a dispute be settled by comparing evidence for
 * `birth_date` specifically, not for a whole person record.
 */
class Citation extends Model
{
    protected $table = 'citations';

    /** @var list<string> */
    protected $fillable = [
        'source_id',
        'citable_type',
        'citable_id',
        'field',
        'page_or_locator',
        'quote',
        'confidence',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confidence' => Certainty::class,
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function citable(): MorphTo
    {
        return $this->morphTo();
    }
}
