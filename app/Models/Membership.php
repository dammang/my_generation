<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * Belonging: "I am a member of the Zomi tribe." Capability is separate — see
 * the scope_role_user table.
 */
class Membership extends Model
{
    use HasUlid;

    protected $table = 'memberships';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'scope_id',
        'status',
        'approved_by',
        'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'approved_at' => 'datetime',
        ];
    }
}
