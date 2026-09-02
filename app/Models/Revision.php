<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionAction;
use Illuminate\Database\Eloquent\Model;

/**
 * The field-level genealogical ledger. Immutable: never updated, never deleted.
 * Values are JSON so a date, an enum and a foreign key id all round-trip losslessly.
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'revisions';

    /** @var list<string> */
    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'field',
        'old_value',
        'new_value',
        'action',
        'reason',
        'source_id',
        'change_request_id',
        'changed_by',
        'ip_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => RevisionAction::class,
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
