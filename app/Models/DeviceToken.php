<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration. Notifications ship to the database channel in MVP; this
 * exists so enabling push in v2 needs no schema change.
 */
class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'app_version',
        'last_seen_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
