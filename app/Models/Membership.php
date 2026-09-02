<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Active);
    }
}
