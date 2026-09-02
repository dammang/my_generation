<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Verification\ApplyChangeRequest;
use App\Actions\Verification\SubmitChangeRequest;
use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\ChangeRequestSupersededException;
use App\Exceptions\GenealogyRuleException;
use App\Models\ChangeRequest;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChangeRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private User $contributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->tribe = Tribe::factory()->create();
        $this->contributor = User::factory()->create();
    }

    private function verifier(): User
    {
        $user = User::factory()->create();
        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName('historian', 'web')->id,
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        app(PermissionResolver::class)->forget($user);

        return $user;
    }

    private function person(): Person
    {
        $person = Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'verification_status' => VerificationStatus::Verified,
        ]);

        $person->setUncertainDate('birth', '1921')->save();

        return $person->refresh();
    }

    private function propose(Person $person, array $payload, ?string $reason = null): ChangeRequest
    {
        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();

        return app(SubmitChangeRequest::class)->handle(
            requester: $this->contributor,
            operation: ChangeRequestOperation::Update,
            target: $person,
            payload: $payload,
            scope: $scope,
            reason: $reason,
        );
    }

    public function test_a_proposal_records_a_diff_against_the_current_state(): void
    {
        $person = $this->person();

        $request = $this->propose($person, ['birth_date' => '1923-01-01'], 'Baptism register, entry 114');

        $this->assertSame(ChangeRequestStatus::Pending, $request->status);
        $this->assertSame(['1921-01-01', '1923-01-01'], $request->diff['birth_date']);
    }

    public function test_approving_applies_the_change_and_records_a_revision(): void
    {
        // The motivating case from the architecture: 1921 corrected to 1923.
        $person = $this->person();
        $request = $this->propose($person, ['birth_date' => '1923-01-01'], 'Baptism register, entry 114');

        app(ApplyChangeRequest::class)->handle($request, $this->verifier());

        // birth_year is derived from birth_date by the observer, so correcting
        // the date is what moves the year.
        $this->assertSame(1923, $person->fresh()->birth_year);
        $this->assertSame(ChangeRequestStatus::Approved, $request->fresh()->status);

        $this->assertDatabaseHas('revisions', [
            'revisionable_id' => $person->id,
            'field' => 'birth_date',
            'change_request_id' => $request->id,
            'reason' => 'Baptism register, entry 114',
        ]);
    }

    public function test_approving_marks_the_record_verified(): void
    {
        $person = Person::factory()->create(['tribe_id' => $this->tribe->id]);
        $request = $this->propose($person, ['first_name' => 'Corrected']);

        $verifier = $this->verifier();
        app(ApplyChangeRequest::class)->handle($request, $verifier);

        $person->refresh();

        $this->assertSame(VerificationStatus::Verified, $person->verification_status);
        $this->assertSame($verifier->id, $person->verified_by);
    }

    public function test_a_record_that_moved_since_the_proposal_is_marked_superseded(): void
    {
        // How concurrent edits are handled without holding a lock across a
        // human decision: the reviewer sees the conflict instead of silently
        // overwriting whatever somebody else corrected.
        $person = $this->person();
        $request = $this->propose($person, ['birth_date' => '1923-01-01']);

        $person->setUncertainDate('birth', '1925')->save();

        try {
            app(ApplyChangeRequest::class)->handle($request, $this->verifier());
            $this->fail('Expected the proposal to be rejected as superseded');
        } catch (ChangeRequestSupersededException $e) {
            $this->assertArrayHasKey('birth_date', $e->conflicts);
            $this->assertSame(['1921-01-01', '1925-01-01'], $e->conflicts['birth_date']);
        }

        $this->assertSame(ChangeRequestStatus::Superseded, $request->fresh()->status);
        $this->assertSame(1925, $person->fresh()->birth_year, 'The other edit stands');
    }

    public function test_a_user_without_review_permission_cannot_approve(): void
    {
        $request = $this->propose($this->person(), ['birth_date' => '1923-01-01']);

        $this->expectException(AuthorizationException::class);

        app(ApplyChangeRequest::class)->handle($request, User::factory()->create());
    }

    public function test_permission_is_rechecked_at_apply_time_not_review_time(): void
    {
        // A role revoked between opening the queue and clicking approve must
        // take effect.
        $person = $this->person();
        $request = $this->propose($person, ['birth_date' => '1923-01-01']);
        $verifier = $this->verifier();

        DB::table('scope_role_user')->where('user_id', $verifier->id)->delete();
        app(PermissionResolver::class)->forget($verifier);

        $this->expectException(AuthorizationException::class);

        app(ApplyChangeRequest::class)->handle($request, $verifier);
    }

    public function test_rejecting_records_the_decision_without_touching_the_record(): void
    {
        $person = $this->person();
        $request = $this->propose($person, ['birth_date' => '1923-01-01']);

        app(ApplyChangeRequest::class)->reject($request, $this->verifier(), 'Source does not support this.');

        $this->assertSame(1921, $person->fresh()->birth_year);
        $this->assertSame(ChangeRequestStatus::Rejected, $request->fresh()->status);
        $this->assertDatabaseHas('change_request_reviews', [
            'change_request_id' => $request->id,
            'decision' => 'reject',
            'comment' => 'Source does not support this.',
        ]);
    }

    public function test_a_derived_field_cannot_be_proposed_directly(): void
    {
        // It would appear accepted and then silently do nothing, because the
        // observer recomputes it from birth_date before the row is written.
        $this->expectException(GenealogyRuleException::class);

        $this->propose($this->person(), ['birth_year' => 1923]);
    }

    public function test_a_decided_request_cannot_be_applied_twice(): void
    {
        $request = $this->propose($this->person(), ['birth_date' => '1923-01-01']);
        $verifier = $this->verifier();

        app(ApplyChangeRequest::class)->handle($request, $verifier);

        $this->expectException(GenealogyRuleException::class);

        app(ApplyChangeRequest::class)->handle($request->fresh(), $verifier);
    }

    public function test_approval_credits_the_contributor(): void
    {
        $request = $this->propose($this->person(), ['birth_date' => '1923-01-01']);

        app(ApplyChangeRequest::class)->handle($request, $this->verifier());

        $this->assertSame(
            1,
            (int) DB::table('contribution_stats')->where('user_id', $this->contributor->id)->value('changes_approved')
        );
    }
}
