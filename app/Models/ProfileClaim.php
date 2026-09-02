<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * "This person is me." A claim never auto-approves.
 */
class ProfileClaim extends Model
{
    use HasUlid;

    protected $table = 'profile_claims';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'person_id',
        'status',
        'evidence',
        'relationship_statement',
        'supporting_media_id',
        'verified_by_kin_user_id',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
