<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Security and administrative actions only — logins, role grants, merges, privacy
 * changes, claim approvals. Genealogical changes go to `revisions`; keeping the
 * two apart is what makes either usable.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'context',
        'ip_hash',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
