<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyLevel;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Model;

/**
 * A public link that can never widen visibility beyond max_privacy_level. Living
 * people are masked regardless of what the link permits.
 */
class ShareLink extends Model
{
    use HasUlid, SoftDeletesWithUniqueness;

    protected $table = 'share_links';

    /** @var list<string> */
    protected $fillable = [
        'token',
        'shareable_type',
        'shareable_id',
        'created_by',
        'max_privacy_level',
        'ancestors',
        'descendants',
        'expires_at',
        'revoked_at',
        'view_count',
        'last_viewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_privacy_level' => PrivacyLevel::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'ancestors' => 'integer',
            'descendants' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
