<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotency ledger for offline writes. A replayed operation returns the stored
 * response instead of executing again — so a client that loses its acknowledgement
 * and retries never creates a second grandfather.
 */
class SyncOperation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sync_operations';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'client_operation_id',
        'endpoint',
        'request_hash',
        'status',
        'response_code',
        'response_body',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
