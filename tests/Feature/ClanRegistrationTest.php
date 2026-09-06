<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Clans\DecideClanRegistration;
use App\Actions\Clans\SubmitClanRegistration;
use App\Enums\ClanRegistrationStatus;
use App\Enums\MembershipStatus;
use App\Exceptions\GenealogyRuleException;
use App\Models\Clan;
use App\Models\ClanRegistration;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Starting a clan: asked for by a member, decided by whoever runs the tribe,
 * and handed to the person who asked.
 */
class ClanRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->tribe = Tribe::factory()->create();
    }

    private function member(?string $role = null): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $this->tribe->id)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function request(User $requester): ClanRegistration
    {
        return app(SubmitClanRegistration::class)->handle($requester, $this->tribe, [
            'name' => 'Guite',
            'statement' => 'My grandfather always said we were Guite.',
        ]);
    }

    #[Test]
    public function asking_creates_nothing_but_the_request(): void
    {
        $this->request($this->member());

        // A clan that exists but is not yet real leaks into every listing that
        // forgot to exclude it, and the ones to worry about are the ones
        // nobody thought about. Nothing is created until somebody approves.
        $this->assertSame(0, Clan::count());
        $this->assertDatabaseHas('clan_registrations', [
            'name' => 'Guite',
            'status' => ClanRegistrationStatus::Pending->value,
        ]);
    }

    #[Test]
    public function approving_creates_the_clan_and_hands_it_to_whoever_asked(): void
    {
        $requester = $this->member();
        $registration = $this->request($requester);

        $clan = app(DecideClanRegistration::class)
            ->approve($registration, $this->member('tribe-admin'), 'Known family.');

        $this->assertSame('Guite', $clan->name);
        $this->assertSame($clan->id, $registration->refresh()->clan_id);
        $this->assertSame(ClanRegistrationStatus::Approved, $registration->status);

        // Somebody has to be able to run it. A clan with no committee is one
        // nobody can add a branch to or approve a membership for.
        $scope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $clan->id)->firstOrFail();

        $this->assertTrue(
            DB::table('scope_role_user')
                ->join('roles', 'roles.id', '=', 'scope_role_user.role_id')
                ->where('scope_role_user.user_id', $requester->id)
                ->where('scope_role_user.scope_id', $scope->id)
                ->where('roles.name', 'clan-admin')
                ->exists(),
            'the person who started the clan cannot administer it',
        );

        // And the grant is real, not just a row: scoped to this clan only.
        $this->assertTrue(
            app(PermissionResolver::class)->can($requester->refresh(), 'clans.manage', $scope->path),
        );
    }

    #[Test]
    public function nobody_decides_their_own_request(): void
    {
        $requester = $this->member('tribe-admin');
        $registration = $this->request($requester);

        // Being able to approve clan registrations is not the same as being
        // able to approve your own: starting a clan and granting yourself its
        // committee in one move is how somebody quietly acquires a family.
        $this->expectException(AuthorizationException::class);

        app(DecideClanRegistration::class)->approve($registration, $requester);
    }

    #[Test]
    public function an_ordinary_member_cannot_decide(): void
    {
        $registration = $this->request($this->member());

        $this->expectException(AuthorizationException::class);

        app(DecideClanRegistration::class)->approve($registration, $this->member('contributor'));
    }

    #[Test]
    public function the_same_request_twice_is_refused(): void
    {
        $requester = $this->member();
        $this->request($requester);

        // A tap that looks like it did nothing otherwise produces four
        // identical requests, and whoever reviews them has to work out they
        // are the same one.
        $this->expectException(GenealogyRuleException::class);

        $this->request($requester);
    }

    #[Test]
    public function a_decided_request_cannot_be_decided_again(): void
    {
        $registration = $this->request($this->member());
        $admin = $this->member('tribe-admin');

        app(DecideClanRegistration::class)->approve($registration, $admin);

        $this->expectException(GenealogyRuleException::class);

        app(DecideClanRegistration::class)->approve($registration->refresh(), $admin);
    }
}
