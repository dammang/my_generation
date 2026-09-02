<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Verification\SubmitChangeRequest;
use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Enums\DuplicateStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\DuplicateCandidates\Pages\ListDuplicateCandidates;
use App\Models\DuplicateCandidate;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->tribe = Tribe::factory()->create();
    }

    private function withScopedRole(string $role): User
    {
        $user = User::factory()->create();
        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName($role, 'web')->id,
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        app(PermissionResolver::class)->forget($user);

        return $user;
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_a_super_admin_can_open_the_panel(): void
    {
        $this->assertTrue(
            User::factory()->create(['is_super_admin' => true])->canAccessPanel(filament()->getPanel('admin'))
        );
    }

    public function test_a_scoped_clan_admin_can_open_the_panel(): void
    {
        $this->assertTrue(
            $this->withScopedRole('clan-admin')->canAccessPanel(filament()->getPanel('admin'))
        );
    }

    public function test_a_historian_can_open_the_panel(): void
    {
        $this->assertTrue(
            $this->withScopedRole('historian')->canAccessPanel(filament()->getPanel('admin'))
        );
    }

    public function test_a_plain_contributor_cannot_open_the_panel(): void
    {
        // The panel exposes the verification queue, merging and role
        // assignment; membership alone is not standing to see any of that.
        $this->assertFalse(
            $this->withScopedRole('contributor')->canAccessPanel(filament()->getPanel('admin'))
        );
    }

    public function test_a_suspended_administrator_cannot_open_the_panel(): void
    {
        $user = $this->withScopedRole('clan-admin');
        // status is deliberately not mass-assignable — suspension is an
        // administrative act, not something a request payload can carry.
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->assertFalse($user->fresh()->canAccessPanel(filament()->getPanel('admin')));
    }

    public function test_the_panel_redirects_an_unauthenticated_visitor_to_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    // ── Verification queue, end to end ───────────────────────────────────

    public function test_a_verifier_can_approve_a_change_request_from_the_queue(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);
        $person->setUncertainDate('birth', '1921')->save();

        $request = app(SubmitChangeRequest::class)->handle(
            requester: User::factory()->create(),
            operation: ChangeRequestOperation::Update,
            target: $person,
            payload: ['birth_date' => '1923-01-01'],
            scope: Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->first(),
            reason: 'Baptism register, entry 114',
        );

        Livewire::actingAs($this->withScopedRole('historian'))
            ->test(ListChangeRequests::class)
            ->callTableAction('approve', $request, ['comment' => 'Confirmed against the register.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1923, $person->fresh()->birth_year);
        $this->assertSame(ChangeRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_the_queue_lists_pending_requests_by_default(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);

        $pending = app(SubmitChangeRequest::class)->handle(
            requester: User::factory()->create(),
            operation: ChangeRequestOperation::Update,
            target: $person,
            payload: ['first_name' => 'Proposed'],
        );

        $decided = app(SubmitChangeRequest::class)->handle(
            requester: User::factory()->create(),
            operation: ChangeRequestOperation::Update,
            target: $person,
            payload: ['last_name' => 'Decided'],
        );
        $decided->update(['status' => ChangeRequestStatus::Approved]);

        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListChangeRequests::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$decided]);
    }

    // ── Merge, end to end ────────────────────────────────────────────────

    public function test_an_admin_can_merge_a_duplicate_from_the_panel(): void
    {
        $winner = Person::factory()->create(['tribe_id' => $this->tribe->id, 'first_name' => 'Pau', 'last_name' => 'Zam']);
        $loser = Person::factory()->create(['tribe_id' => $this->tribe->id, 'first_name' => 'Pau', 'last_name' => 'Zamm']);

        $candidate = DuplicateCandidate::create([
            'person_a_id' => min($winner->id, $loser->id),
            'person_b_id' => max($winner->id, $loser->id),
            'score' => 0.91,
            'signals' => ['name_phonetic' => true, 'birth_year' => 0.67],
            'status' => DuplicateStatus::Open,
        ]);

        $keepA = $candidate->person_a_id === $winner->id;

        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListDuplicateCandidates::class)
            ->callTableAction('merge', $candidate, ['keep' => $keepA ? 'a' : 'b'])
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('people', ['id' => $loser->id]);
        $this->assertSame($winner->id, $loser->fresh()->merged_into_person_id);
        $this->assertSame(DuplicateStatus::Merged, $candidate->fresh()->status);
        $this->assertDatabaseHas('person_merges', [
            'winner_person_id' => $winner->id,
            'loser_person_id' => $loser->id,
        ]);
    }

    public function test_keeping_two_records_separate_closes_the_candidate_without_merging(): void
    {
        $a = Person::factory()->create(['tribe_id' => $this->tribe->id]);
        $b = Person::factory()->create(['tribe_id' => $this->tribe->id]);

        $candidate = DuplicateCandidate::create([
            'person_a_id' => min($a->id, $b->id),
            'person_b_id' => max($a->id, $b->id),
            'score' => 0.84,
            'status' => DuplicateStatus::Open,
        ]);

        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListDuplicateCandidates::class)
            ->callTableAction('keepSeparate', $candidate)
            ->assertHasNoTableActionErrors();

        $this->assertSame(DuplicateStatus::KeptSeparate, $candidate->fresh()->status);
        $this->assertNotSoftDeleted('people', ['id' => $b->id]);
    }
}
