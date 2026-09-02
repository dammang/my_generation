<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUlid;
use App\Models\Concerns\SoftDeletesWithUniqueness;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A login account.
 *
 * A user is NOT a person. Genealogy records exist for people who never had an
 * account and never will, and `person_id` is set only by an approved
 * ProfileClaim — never by registration and never by client input.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, HasUlid, Notifiable, SoftDeletesWithUniqueness;

    /**
     * Roles and permissions are seeded for the `web` guard. Sanctum
     * authenticates through the `sanctum` guard and makes it the default for
     * the rest of the request, at which point an unpinned Spatie lookup asks
     * for roles under a guard that has none. Pinning it makes role checks
     * answer the same way whoever is asking.
     */
    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'avatar_media_id',
    ];

    /**
     * In-memory defaults matching the column defaults. Without these a
     * just-created model reports null for status until it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => UserStatus::Active->value,
        'locale' => 'en',
        'is_super_admin' => false,
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

    /**
     * Who may open the admin panel.
     *
     * Membership alone is never enough: the panel exposes the verification
     * queue, duplicate merging and role assignment, so it is gated on holding
     * an administrative or verifying role somewhere. The panel is not a
     * privacy bypass either — every resource still runs the same policies as
     * the API.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($this->status !== UserStatus::Active) {
            return false;
        }

        $adminRoles = ['tribe-admin', 'clan-admin', 'family-admin', 'historian'];

        if ($this->hasAnyRole($adminRoles)) {
            return true;
        }

        return DB::table('scope_role_user')
            ->join('roles', 'roles.id', '=', 'scope_role_user.role_id')
            ->where('scope_role_user.user_id', $this->getKey())
            ->whereIn('roles.name', $adminRoles)
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
