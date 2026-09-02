<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * A proposed change. Verified genealogy is never silently overwritten.
 *
 * `original_snapshot` is what makes concurrent edits safe without locking: at
 * apply time the record's current state is compared against it, and one that
 * moved is marked superseded with a three-way diff.
 *
 * Append-only — never soft-deleted, because it is half the audit trail.
 */
class ChangeRequest extends Model
{
    use HasUlid;

    protected $table = 'change_requests';

    /** @var list<string> */
    protected $fillable = [
        'operation',
        'target_type',
        'target_id',
        'parent_change_request_id',
        'payload',
        'original_snapshot',
        'diff',
        'scope_id',
        'reason',
        'source_id',
        'status',
        'applied_at',
        'applied_revision_ids',
        'client_operation_id',
        'requested_by',
        'decided_by',
        'decided_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operation' => ChangeRequestOperation::class,
            'status' => ChangeRequestStatus::class,
            'payload' => 'array',
            'original_snapshot' => 'array',
            'diff' => 'array',
            'applied_revision_ids' => 'array',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
