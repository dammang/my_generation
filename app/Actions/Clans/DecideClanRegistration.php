<?php

declare(strict_types=1);

namespace App\Actions\Clans;

use App\Actions\Access\AssignScopedRole;
use App\Enums\ClanRegistrationStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\AuditLog;
use App\Models\Clan;
use App\Models\ClanRegistration;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Approving a clan creates it, and hands it to the person who asked.
 *
 * The requester becomes its administrator because somebody has to be: a clan
 * with no committee is one nobody can add a family branch to, approve a
 * membership for, or correct — and the person who cared enough to start it is
 * the obvious first one. They can appoint the rest themselves.
 */
class DecideClanRegistration
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ViewerScopeResolver $scopes,
        private readonly AssignScopedRole $assignRole,
    ) {}

    public function approve(ClanRegistration $registration, User $decidedBy, ?string $note = null): Clan
    {
        $this->assertMayDecide($registration, $decidedBy);

        return DB::transaction(function () use ($registration, $decidedBy, $note): Clan {
            $clan = Clan::create([
                'tribe_id' => $registration->tribe_id,
                'parent_clan_id' => $registration->parent_clan_id,
                'name' => $registration->name,
                'slug' => $this->slugFor($registration),
                'native_name' => $registration->native_name,
                'description' => $registration->description,
                'ancestor_person_id' => $registration->ancestor_person_id,
            ]);

            $registration->forceFill([
                'status' => ClanRegistrationStatus::Approved,
                'decided_by' => $decidedBy->getKey(),
                'decided_at' => now(),
                'decision_note' => $note,
                'clan_id' => $clan->getKey(),
            ])->save();

            $this->makeRequesterAdministrator($registration, $clan, $decidedBy);

            AuditLog::create([
                'user_id' => $decidedBy->getKey(),
                'action' => 'clan.registration_approved',
                'auditable_type' => $registration->getMorphClass(),
                'auditable_id' => $registration->getKey(),
                'context' => ['clan' => $clan->ulid, 'requester' => $registration->requester?->ulid],
            ]);

            return $clan;
        });
    }

    public function reject(ClanRegistration $registration, User $decidedBy, ?string $note = null): ClanRegistration
    {
        $this->assertMayDecide($registration, $decidedBy);

        $registration->forceFill([
            'status' => ClanRegistrationStatus::Rejected,
            'decided_by' => $decidedBy->getKey(),
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $registration;
    }

    /**
     * A URL-safe name, unique within the tribe.
     *
     * Two families genuinely can be called the same thing in one tribe — a
     * name is not an identifier — so a collision suffixes rather than refuses.
     * Refusing here would reject an approval for a reason the approver cannot
     * see and cannot fix.
     */
    private function slugFor(ClanRegistration $registration): string
    {
        $base = Str::slug($registration->name) ?: 'clan';
        $slug = $base;
        $n = 2;

        while (Clan::where('tribe_id', $registration->tribe_id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * Gives the requester the clan's own admin role.
     *
     * Scoped to the new clan and nothing above it: starting a clan makes
     * somebody responsible for that clan, not for the tribe that approved it.
     */
    private function makeRequesterAdministrator(ClanRegistration $registration, Clan $clan, User $decidedBy): void
    {
        $role = Role::where('name', 'clan-admin')->where('guard_name', 'web')->first();
        $scope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $clan->getKey())->first();

        if ($role === null || $scope === null || $registration->requester === null) {
            // Loudly, because a clan whose founder cannot administer it is the
            // one thing this whole flow exists to avoid.
            throw new GenealogyRuleException(
                'The clan was created but could not be handed to anybody: no clan-admin role, or no scope.',
                'CLAN_HANDOVER_FAILED',
            );
        }

        // Through the action that owns this, rather than writing the pivot
        // here: it checks the granter may assign roles in the scope, refuses
        // escalation, and knows the table's actual shape. A second copy of
        // that would eventually be a different copy.
        $this->assignRole->handle($decidedBy, $registration->requester, $role, $scope);

        // What they may see has just changed, and that answer is cached.
        $this->scopes->forget($registration->requester);
    }

    private function assertMayDecide(ClanRegistration $registration, User $decidedBy): void
    {
        if (! $registration->isPending()) {
            throw new GenealogyRuleException('This request has already been decided.', 'CLAN_REGISTRATION_DECIDED');
        }

        if ($decidedBy->is($registration->requester)) {
            throw new AuthorizationException('You cannot decide your own request.');
        }

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $registration->tribe_id)
            ->first();

        if ($scope === null || ! $this->permissions->can($decidedBy, 'clans.manage', $scope->path)) {
            throw new AuthorizationException('You may not decide clan registrations in this tribe.');
        }
    }
}
