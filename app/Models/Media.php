<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\MediaStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A file on an S3-compatible disk. MySQL holds the pointer, the provenance and
 * the checksum — never the binary.
 */
class Media extends Model
{
    use HasFactory, HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'media';

    /** @var list<string> */
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'width',
        'height',
        'duration_seconds',
        'conversions',
        'is_private',
        'caption',
        'taken_at',
        'place_id',
        'uploaded_by',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'collection' => MediaCollection::class,
            'status' => MediaStatus::class,
            'conversions' => 'array',
            'is_private' => 'boolean',
            'taken_at' => 'date',
            'size_bytes' => 'integer',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
