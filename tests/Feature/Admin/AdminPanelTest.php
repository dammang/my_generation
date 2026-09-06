<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Verification\SubmitChangeRequest;
use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Enums\ClaimStatus;
use App\Enums\DuplicateStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\DuplicateCandidates\Pages\ListDuplicateCandidates;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\ProfileClaims\Pages\ListProfileClaims;
use App\Filament\Widgets\ArchiveOverview;
use App\Models\DuplicateCandidate;
use App\Models\Person;
use App\Models\ProfileClaim;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\Pages\EditProfile;
use Filament\Widgets\StatsOverviewWidget\Stat;
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

    public function test_every_dashboard_card_links_somewhere_that_exists(): void
    {
        $widget = Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ArchiveOverview::class)
            ->instance();

        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        $this->assertCount(6, $stats, 'a card was added or removed without updating this test');

        foreach ($stats as $stat) {
            $url = $stat->getUrl();

            // route() throws on a name that does not exist, so a typo here is
            // a 500 on the first page every admin loads. Building the widget
            // at all is most of the assertion; this is the rest of it.
            $this->assertNotNull($url, $stat->getLabel().' does not go anywhere');
            $this->assertStringStartsWith('http', $url);
        }
    }

    public function test_the_verified_card_opens_only_the_verified_people(): void
    {
        $tribe = $this->tribe;

        Person::factory()->create([
            'tribe_id' => $tribe->id,
            'verification_status' => VerificationStatus::Verified,
            'first_name' => 'Checked',
        ]);
        Person::factory()->create([
            'tribe_id' => $tribe->id,
            'verification_status' => VerificationStatus::Unverified,
            'first_name' => 'Unchecked',
        ]);

        // The filter the dashboard link carries. Without it the card reports a
        // subset and opens the whole list, which is worse than not linking.
        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListPeople::class)
            ->set('tableFilters.verification_status.value', VerificationStatus::Verified->value)
            ->assertCanSeeTableRecords(Person::where('first_name', 'Checked')->get())
            ->assertCanNotSeeTableRecords(Person::where('first_name', 'Unchecked')->get());
    }

    public function test_a_super_admin_can_edit_a_person(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);

        // Filament hides an action the policy denies, so a missing Edit button
        // and a refused save are the same fault wearing different clothes.
        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListPeople::class)
            ->assertTableActionVisible('edit', $person);
    }

    public function test_an_admin_can_reach_a_page_to_edit_their_own_account(): void
    {
        // The user menu showed a name and offered nothing to do with it: no
        // way to change your own password, and no way to correct the name that
        // signs every audit entry in the archive.
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('filament.admin.auth.profile'))
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Password');
    }

    public function test_changing_an_email_does_not_grant_a_verified_address(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
            'email' => 'before@example.com',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => 'after@example.com',
                // Required, and rightly so: an unattended session should not
                // be able to move the account to somebody else's address.
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Held, not applied. This app refuses contributions from an unverified
        // address, so writing the new one straight in would leave somebody
        // verified against an address they might never have owned.
        $this->assertSame('before@example.com', $user->refresh()->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a_super_admin_can_approve_a_profile_claim(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);
        $claimant = User::factory()->create();

        $claim = ProfileClaim::create([
            'user_id' => $claimant->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Pending,
            'relationship_statement' => 'This is me.',
        ]);

        // The API could approve these since claims shipped; nothing in the
        // product could reach it, so every request sat pending forever.
        Livewire::actingAs(User::factory()->create(['is_super_admin' => true]))
            ->test(ListProfileClaims::class)
            ->callTableAction('approve', $claim, ['note' => 'Known to me.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ClaimStatus::Approved, $claim->refresh()->status);

        // The point of approving: the account is now that person.
        $this->assertSame($person->id, $claimant->refresh()->person_id);
    }

    public function test_nobody_can_approve_their_own_claim(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $claim = ProfileClaim::create([
            'user_id' => $admin->id,
            'person_id' => $person->id,
            'status' => ClaimStatus::Pending,
        ]);

        Livewire::actingAs($admin)
            ->test(ListProfileClaims::class)
            ->callTableAction('approve', $claim, ['note' => null]);

        // Being a super admin is not an exemption from this one: deciding your
        // own claim is how somebody quietly becomes a member of a family.
        $this->assertSame(ClaimStatus::Pending, $claim->refresh()->status);
        $this->assertNull($admin->refresh()->person_id);
    }

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
