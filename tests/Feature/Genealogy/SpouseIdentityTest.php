<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Actions\Genealogy\AddChildToUnion;
use App\Actions\Verification\ApplyChangeRequest;
use App\Models\ChangeRequest;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A woman who married in is usually in the archive twice: once beside her
 * husband, once among her own parents. Saying so is how she gets a generation
 * of her own, and how her family becomes reachable from his.
 */
class SpouseIdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['is_super_admin' => true]);
    }

    #[Test]
    public function claiming_is_a_proposal_and_changes_nothing_yet(): void
    {
        [$wife, $daughter] = $this->twoRecordsOfOneWoman();

        $this->actingAs($this->user)
            ->postJson(route('api.v1.people.identity', $wife), [
                'person_ulid' => $daughter->ulid,
                'reason' => 'Same woman — my aunt.',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.change_request.status', 'pending');

        // Merging two records is the hardest thing in the archive to undo in a
        // family's understanding of itself. Nothing happens until somebody
        // with standing agrees.
        $this->assertNull($wife->refresh()->merged_into_person_id);
        $this->assertNull($daughter->refresh()->merged_into_person_id);
    }

    #[Test]
    public function approving_makes_them_one_person_with_both_families(): void
    {
        [$wife, $daughter, $husband, $mother] = $this->twoRecordsOfOneWoman();

        $this->actingAs($this->user)
            ->postJson(route('api.v1.people.identity', $wife), [
                'person_ulid' => $daughter->ulid,
            ])
            ->assertStatus(202);

        $request = ChangeRequest::latest('id')->firstOrFail();

        app(ApplyChangeRequest::class)->handle($request, $this->user);

        // The record with a family of its own wins: it carries the parents,
        // which is the whole reason for the link.
        $survivor = $daughter->refresh();

        $this->assertNull($survivor->merged_into_person_id);
        $this->assertSame($survivor->id, $wife->refresh()->merged_into_person_id);

        // She is now both: her mother's daughter and her husband's wife.
        $this->assertTrue(
            $survivor->parents()->pluck('people.id')->contains($mother->id),
            'she lost her own parents in the merge',
        );

        $this->assertTrue(
            Union::query()
                ->where(fn ($q) => $q->where('partner_1_id', $survivor->id)
                    ->orWhere('partner_2_id', $survivor->id))
                ->exists(),
            'she lost her marriage in the merge',
        );

        $this->assertNotNull($husband->refresh());
    }

    #[Test]
    public function a_record_cannot_be_claimed_as_itself(): void
    {
        [$wife] = $this->twoRecordsOfOneWoman();

        $this->actingAs($this->user)
            ->postJson(route('api.v1.people.identity', $wife), [
                'person_ulid' => $wife->ulid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MERGE_SELF');
    }

    /**
     * The same woman twice: a wife beside her husband, and a daughter beside
     * her mother.
     *
     * @return array{0: Person, 1: Person, 2: Person, 3: Person}
     */
    private function twoRecordsOfOneWoman(): array
    {
        $husband = Person::factory()->bornExactly(1940)->create();
        $wife = Person::factory()->bornExactly(1945)->create(['display_name' => 'Cing Man']);

        Union::factory()->create([
            'partner_1_id' => $husband->id,
            'partner_2_id' => $wife->id,
        ]);

        $mother = Person::factory()->bornExactly(1920)->create();
        $daughter = Person::factory()->bornExactly(1945)->create(['display_name' => 'Cing Man']);

        $union = Union::factory()->create(['partner_1_id' => $mother->id]);
        app(AddChildToUnion::class)->handle($union, $daughter);

        return [$wife, $daughter, $husband, $mother];
    }
}
