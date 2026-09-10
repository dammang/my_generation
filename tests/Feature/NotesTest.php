<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\NoteAudience;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;
use App\Services\Tree\LineageDepthService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Notes on a person, and who may read them.
 *
 * Records go quiet: somebody hides their name and there is then no way to say
 * who they were to the people entitled to know. A note is that. The audience
 * is the whole feature — a note read by the wrong person is worse than no note
 * at all, because it was written in the belief that it would not be.
 */
class NotesTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private Person $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Cache::flush();

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $this->subject = $this->person();
    }

    public function test_any_member_may_write_a_note(): void
    {
        $member = $this->accountFor($this->person());

        $this->actingAs($member)
            ->postJson(route('api.v1.people.notes.store', $this->subject), [
                'body' => 'He is the one they called Pau.',
                'audience' => NoteAudience::Clan->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'He is the one they called Pau.');
    }

    public function test_a_note_for_descendants_reaches_them(): void
    {
        [$author, $child, $grandchild] = $this->threeGenerations();

        $this->writeAs($author, NoteAudience::Descendants);

        foreach ([$child, $grandchild] as $heir) {
            $this->readAs($this->accountFor($heir))->assertJsonCount(1, 'data');
        }
    }

    public function test_a_note_for_descendants_reaches_nobody_else(): void
    {
        [$author] = $this->threeGenerations();

        $this->writeAs($author, NoteAudience::Descendants);

        // In the clan, and not of that line. This is the case the writer is
        // relying on: "other family can not see".
        $this->readAs($this->accountFor($this->person()))->assertJsonCount(0, 'data');
    }

    public function test_the_writer_reads_their_own_note_whatever_the_audience(): void
    {
        $account = $this->writeAs($this->person(), NoteAudience::Private);

        $this->readAs($account)->assertJsonCount(1, 'data');
    }

    public function test_only_me_means_only_me(): void
    {
        [$author, $child] = $this->threeGenerations();

        $this->writeAs($author, NoteAudience::Private);

        // Not their own children, and not an administrator either: a scale
        // that quietly admits somebody else is not the scale it says it is.
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->readAs($this->accountFor($child))->assertJsonCount(0, 'data');
        $this->readAs($admin)->assertJsonCount(0, 'data');
    }

    public function test_a_clan_note_reaches_the_clan(): void
    {
        $this->writeAs($this->person(), NoteAudience::Clan);

        $this->readAs($this->accountFor($this->person()))->assertJsonCount(1, 'data');
    }

    public function test_a_clan_note_stops_at_the_clan(): void
    {
        $this->writeAs($this->person(), NoteAudience::Clan);

        // In the tribe, and in no clan of it.
        $outsider = User::factory()->create();

        Membership::create([
            'user_id' => $outsider->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')
                ->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        app(ViewerScopeResolver::class)->forget($outsider);

        $this->actingAs($outsider)
            ->getJson(route('api.v1.people.notes', $this->subject))
            ->assertNotFound();
    }

    public function test_somebody_with_no_record_cannot_write_to_a_line_they_do_not_have(): void
    {
        // Said plainly rather than written and left silent: a note for the
        // descendants of nobody is a note nobody can ever read.
        $stranger = $this->accountFor(null);

        $this->actingAs($stranger)
            ->postJson(route('api.v1.people.notes.store', $this->subject), [
                'body' => 'Anything.',
                'audience' => NoteAudience::Descendants->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audience']);
    }

    public function test_the_writer_may_remove_it_and_nobody_else_may(): void
    {
        $account = $this->writeAs($this->person(), NoteAudience::Clan);
        $note = Note::firstOrFail();

        $this->actingAs($this->accountFor($this->person()))
            ->deleteJson(route('api.v1.notes.destroy', $note))
            ->assertForbidden();

        $this->actingAs($account)
            ->deleteJson(route('api.v1.notes.destroy', $note))
            ->assertNoContent();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    /** @return array{0: Person, 1: Person, 2: Person} */
    private function threeGenerations(): array
    {
        $author = $this->person();
        $child = $this->person();
        $grandchild = $this->person();

        Relationship::factory()->parentChild($author, $child)->create();
        Relationship::factory()->parentChild($child, $grandchild)->create();

        app(LineageDepthService::class)->recomputeFor($author);

        return [$author, $child, $grandchild];
    }

    private function writeAs(Person $author, NoteAudience $audience): User
    {
        $account = $this->accountFor($author);

        $this->actingAs($account)
            ->postJson(route('api.v1.people.notes.store', $this->subject), [
                'body' => 'He is the one they called Pau.',
                'audience' => $audience->value,
            ])
            ->assertCreated();

        return $account;
    }

    private function readAs(User $reader): TestResponse
    {
        return $this->actingAs($reader)
            ->getJson(route('api.v1.people.notes', $this->subject))
            ->assertOk();
    }

    /**
     * Born in a fixed year on purpose. The factory picks one anywhere back to
     * 1900, and anybody born before the living-age cutoff counts as deceased —
     * which lifts their privacy to the tribe's default and changes who may see
     * them. Left to chance, these tests passed or failed by the year the faker
     * happened to draw.
     */
    private function person(): Person
    {
        return Person::factory()->bornExactly(1990)->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'privacy_level' => PrivacyLevel::Clan,
        ]);
    }

    private function accountFor(?Person $person): User
    {
        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', 'clan')
                ->where('scopeable_id', $this->clan->id)->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        if ($person !== null) {
            $user->forceFill(['person_id' => $person->id])->save();
        }

        // Written straight into the table, which is not how the application
        // grants membership: RequestMembership and DecideMembership both drop
        // the cached entitlements as they go. Without this the scope cached
        // under this id decides the test, and it passed or failed depending on
        // what had run before it.
        app(ViewerScopeResolver::class)->forget($user);

        return $user;
    }
}
