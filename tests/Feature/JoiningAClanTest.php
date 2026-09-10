<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Asking to join a clan, and being asked who you are.
 *
 * A request used to carry an account name and nothing else, which left a
 * reviewer deciding whether a stranger belongs to a family on no evidence at
 * all. These are the questions asked at the door.
 */
class JoiningAClanTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    public function test_asking_to_join_a_clan_records_who_you_say_you_are(): void
    {
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), $this->answers())
            ->assertCreated();

        $membership = Membership::where('user_id', $applicant->id)->firstOrFail();

        $this->assertSame(MembershipStatus::Pending, $membership->status);
        $this->assertSame('Cing Za Man', $membership->applicant_name);
        $this->assertSame('Thawng Dam', $membership->father_name);
        $this->assertSame('Niang Za Dim', $membership->mother_name);
        $this->assertSame('MM', $membership->country);
    }

    public function test_a_clan_will_not_take_an_unanswered_request(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'applicant_name', 'father_name', 'mother_name', 'country', 'contact',
            ]);
    }

    public function test_a_tribe_is_a_wider_door_and_asks_nothing(): void
    {
        // Turning strangers away from a tribe for not knowing their
        // grandmother's name would be a barrier for its own sake.
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();
    }

    public function test_grandparents_may_be_unknown(): void
    {
        // The ordinary state of an oral archive, not an evasion.
        $answers = $this->answers();
        unset($answers['grandfather_name'], $answers['grandmother_name']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.memberships.store'), $answers)
            ->assertCreated();
    }

    public function test_the_selfie_is_stored_away_from_the_family_albums(): void
    {
        // Both, because the controller writes to R2 where it is configured
        // and falls back to local where it is not.
        Storage::fake('local');
        Storage::fake('r2');

        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                ...$this->answers(),
                'photo' => UploadedFile::fake()->image('selfie.jpg', 400, 400),
            ])
            ->assertCreated();

        $membership = Membership::where('user_id', $applicant->id)->firstOrFail();

        $this->assertNotNull($membership->photo_path);
        $this->assertStringStartsWith('join-requests/', $membership->photo_path);

        // Never a media record: it is identification for a reviewer, not a
        // family photograph, and it must not surface in an album or an export.
        $this->assertDatabaseCount('media', 0);
    }

    public function test_only_the_applicant_and_a_reviewer_read_the_answers(): void
    {
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), $this->answers())
            ->assertCreated();

        // The applicant, looking at their own request.
        $this->actingAs($applicant)
            ->getJson(route('api.v1.memberships.index'))
            ->assertOk()
            ->assertJsonPath('data.0.applicant.name', 'Cing Za Man');

        // A reviewer of that clan.
        $this->actingAs($this->clanAdmin())
            ->getJson(route('api.v1.memberships.scope', [
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
                'status' => 'pending',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.applicant.father', 'Thawng Dam')
            // The reviewer needs a way to reach them, guarded by the same rule
            // as everything else they wrote.
            ->assertJsonPath('data.0.applicant.email', $applicant->email);
    }

    public function test_a_stranger_is_not_shown_somebodys_parents(): void
    {
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), $this->answers())
            ->assertCreated();

        // Somebody else's request, read through their own listing: the answers
        // are a stranger's parents, their country and their face.
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.memberships.scope', [
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
            ]))
            ->assertForbidden();
    }

    public function test_a_request_with_nothing_to_say_carries_no_empty_answer(): void
    {
        // A tribe asks nothing, so its memberships have no answers. An empty
        // PHP array serialises as a JSON array rather than an object, and a
        // client reading it as an object crashed on the whole list because one
        // row had nothing in it.
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated();

        $this->actingAs($applicant)
            ->getJson(route('api.v1.memberships.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.applicant');
    }

    /** @return array<string, string> */
    private function answers(): array
    {
        return [
            'scope_type' => 'clan',
            'scope_ulid' => $this->clan->ulid,
            'applicant_name' => 'Cing Za Man',
            'father_name' => 'Thawng Dam',
            'mother_name' => 'Niang Za Dim',
            'grandfather_name' => 'Hau Neng',
            'grandmother_name' => 'Dim Zel',
            'country' => 'MM',
            'contact' => '+95 9 123 456',
        ];
    }

    private function clanAdmin(): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'clan')
            ->where('scopeable_id', $this->clan->id)
            ->firstOrFail();

        $user->assignRole('clan-admin');

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'role_id' => DB::table('roles')->where('name', 'clan-admin')->value('id'),
            'granted_at' => now(),
        ]);

        return $user;
    }
}
