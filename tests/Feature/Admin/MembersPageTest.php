<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\MembershipStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The roll of who belongs, and the two questions asked of it: which clan, and
 * which country.
 */
class MembersPageTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $jk;

    private Clan $sukte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->jk = Clan::factory()->create(['tribe_id' => $this->tribe->id, 'name' => 'JK']);
        $this->sukte = Clan::factory()->create(['tribe_id' => $this->tribe->id, 'name' => 'Sukte']);
    }

    public function test_it_lists_members_and_not_applicants(): void
    {
        $member = $this->member($this->jk, 'MM', 'Cing Za Man');
        $waiting = $this->member($this->jk, 'MM', 'Still Waiting', MembershipStatus::Pending);

        Livewire::actingAs($this->admin())
            ->test(ListMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->assertCanNotSeeTableRecords([$waiting]);
    }

    public function test_it_can_be_narrowed_to_one_clan(): void
    {
        $here = $this->member($this->jk, 'MM', 'Cing Za Man');
        $elsewhere = $this->member($this->sukte, 'MM', 'Somebody Else');

        Livewire::actingAs($this->admin())
            ->test(ListMembers::class)
            ->set('tableFilters.clan.value', $this->jk->id)
            ->assertCanSeeTableRecords([$here])
            ->assertCanNotSeeTableRecords([$elsewhere]);
    }

    public function test_it_can_be_narrowed_to_one_country(): void
    {
        $home = $this->member($this->jk, 'MM', 'Cing Za Man');
        $abroad = $this->member($this->jk, 'MY', 'Somebody Abroad');

        Livewire::actingAs($this->admin())
            ->test(ListMembers::class)
            ->set('tableFilters.country.value', 'MM')
            ->assertCanSeeTableRecords([$home])
            ->assertCanNotSeeTableRecords([$abroad]);
    }

    public function test_the_country_reads_as_a_country(): void
    {
        // Not "MM". A reader wants the country, and the code is what the form
        // happened to store.
        $this->member($this->jk, 'MM', 'Cing Za Man');

        Livewire::actingAs($this->admin())
            ->test(ListMembers::class)
            ->assertSee('Myanmar (Burma)');
    }

    private function member(
        Clan $clan,
        string $country,
        string $name,
        MembershipStatus $status = MembershipStatus::Active,
    ): Membership {
        return Membership::create([
            'user_id' => User::factory()->create()->id,
            'scope_id' => Scope::where('scopeable_type', 'clan')
                ->where('scopeable_id', $clan->id)->value('id'),
            'status' => $status,
            'applicant_name' => $name,
            'father_name' => 'Thawng Dam',
            'mother_name' => 'Niang Za Dim',
            'grandfather_name' => 'Hau Neng',
            'grandmother_name' => 'Dim Zel',
            'country' => $country,
            'contact' => 'someone@example.com',
            'approved_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }
}
