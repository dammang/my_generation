<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Actions\Verification\ApplyChangeRequest;
use App\Models\ChangeRequest;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Link to another family" is asked once, and can be taken back.
 */
class FamilyLinkStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FamilyBranch $husbands;

    private FamilyBranch $hers;

    private Person $wife;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['is_super_admin' => true]);
        $this->husbands = FamilyBranch::factory()->create(['name' => 'Thawng Dam']);
        $this->hers = FamilyBranch::factory()->create(['name' => 'Khup Lian']);

        // Added beside her husband, so she inherited his branch.
        $this->wife = Person::factory()->bornExactly(1950)->create([
            'family_branch_id' => $this->husbands->id,
        ]);
    }

    #[Test]
    public function an_inherited_branch_is_not_mistaken_for_a_link(): void
    {
        $this->assertNull($this->familyLink());
    }

    #[Test]
    public function a_link_waiting_for_review_counts_as_answered(): void
    {
        $this->propose($this->hers->ulid);

        $this->assertSame('pending', $this->familyLink()['state']);
        $this->assertSame('branch', $this->familyLink()['kind']);
        $this->assertSame('Khup Lian', $this->familyLink()['label']);
    }

    #[Test]
    public function an_approved_link_is_in_effect_until_it_is_unlinked(): void
    {
        $this->approve($this->propose($this->hers->ulid));

        $this->assertSame('linked', $this->familyLink()['state']);
        $this->assertSame('Khup Lian', $this->familyLink()['label']);

        // Linked by mistake: taking it back is its own proposal, and once it
        // lands the button is offered again.
        $unlink = $this->propose(null);
        $this->assertSame('unlink', $this->familyLink()['kind']);

        $this->approve($unlink);

        $this->assertNull($this->familyLink());
        $this->assertNull($this->wife->refresh()->family_branch_id);
    }

    #[Test]
    public function the_card_knows_which_link_is_still_true(): void
    {
        $first = $this->propose($this->hers->ulid);
        $this->approve($first);

        $cards = collect(
            $this->actingAs($this->user)
                ->getJson(route('api.v1.changes.index', ['filter' => 'mine']))
                ->assertOk()
                ->json('data')
        )->keyBy('ulid');

        $this->assertSame(['kind' => 'branch', 'in_effect' => true], $cards[$first->ulid]['family_link']);

        // The reviewer reads family names, not row ids.
        $this->assertSame(
            ['Thawng Dam', 'Khup Lian'],
            [$cards[$first->ulid]['diff'][0]['before'], $cards[$first->ulid]['diff'][0]['after']],
        );
        $this->assertSame('Family', $cards[$first->ulid]['diff'][0]['label']);

        $this->approve($this->propose($this->husbands->ulid));

        $this->assertFalse(
            $this->actingAs($this->user)
                ->getJson(route('api.v1.changes.index', ['filter' => 'mine']))
                ->json('data.1.family_link.in_effect'),
            'a link since replaced by another still offered to be unlinked',
        );
    }

    /** @return array<string, mixed>|null */
    private function familyLink(): ?array
    {
        return $this->actingAs($this->user)
            ->getJson(route('api.v1.people.family', $this->wife))
            ->assertOk()
            ->json('data.family_link');
    }

    private function propose(?string $branchUlid): ChangeRequest
    {
        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $this->wife), [
                'family_branch_ulid' => $branchUlid,
            ])
            ->assertStatus(202);

        return ChangeRequest::latest('id')->firstOrFail();
    }

    private function approve(ChangeRequest $request): void
    {
        app(ApplyChangeRequest::class)->handle($request, $this->user);
    }
}
