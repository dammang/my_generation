<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A login account.
 *
 * A user is NOT a person. Genealogy records exist for people who never had an
 * account and never will, and `person_id` is set only by an approved
 * ProfileClaim — never by registration and never by client input.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUlid, Notifiable, SoftDeletesWithUniqueness;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'avatar_media_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_super_admin' => 'boolean',
        ];
    }

    /** The genealogy record this account has been verified as. Usually null. */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
