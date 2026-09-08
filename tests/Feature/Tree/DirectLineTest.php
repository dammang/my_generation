<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Actions\Genealogy\AddChildToUnion;
use App\Actions\Genealogy\AnchorClanGenerations;
use App\Models\Clan;
use App\Models\Person;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The line a family recites: one name per generation, oldest first.
 */
class DirectLineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['is_super_admin' => true]);
        $this->clan = Clan::factory()->create(['tribe_id' => Tribe::factory()->create()->id]);
    }

    #[Test]
    public function it_reads_from_the_oldest_down_to_them(): void
    {
        $line = $this->chain(['Pu Zo', 'Kip Mang', 'Jasuan', 'Thawng Dam']);

        $names = collect(
            $this->actingAs($this->user)
                ->getJson(route('api.v1.tree.line', $line->last()))
                ->assertOk()
                ->json('data')
        )->pluck('display_name');

        // The order it is said in, and the order the generations count.
        $this->assertSame(
            ['Pu Zo', 'Kip Mang', 'Jasuan', 'Thawng Dam'],
            $names->all(),
        );
    }

    #[Test]
    public function it_is_one_chain_and_not_a_tree_of_ancestors(): void
    {
        $line = $this->chain(['Pu Zo', 'Kip Mang']);

        // A mother, recorded but not the line the clan descends through.
        $mother = Person::factory()->bornExactly(1905)->create([
            'clan_id' => $this->clan->id,
            'gender' => 'female',
            'display_name' => 'Ciin Man',
        ]);

        $union = Union::factory()->create([
            'partner_1_id' => $line->first()->id,
            'partner_2_id' => $mother->id,
        ]);

        app(AddChildToUnion::class)->handle($union, $line->last());

        $names = collect(
            $this->actingAs($this->user)
                ->getJson(route('api.v1.tree.line', $line->last()))
                ->assertOk()
                ->json('data')
        )->pluck('display_name');

        // Every ancestor would be a tree — two parents, four grandparents —
        // and no way to read it as a list. One parent per step.
        $this->assertSame(['Pu Zo', 'Kip Mang'], $names->all());
    }

    #[Test]
    public function every_name_carries_both_of_the_clans_numbers(): void
    {
        $line = $this->chain(['Pu Zo', 'Kip Mang', 'Jasuan', 'Thawng Dam']);

        $this->clan->forceFill([
            'ancestor_person_id' => $line->first()->id,
            'counting_origin_person_id' => $line[2]->id,
        ])->save();

        app(AnchorClanGenerations::class)->handle($this->clan);

        $rows = collect(
            $this->actingAs($this->user)
                ->getJson(route('api.v1.tree.line', $line->last()))
                ->assertOk()
                ->json('data')
        );

        // Jasuan is the third from Pu Zo and the first of his own line; the
        // son below him is the fourth and the second. Both are true and the
        // table shows them side by side.
        $jasuan = $rows->firstWhere('display_name', 'Jasuan');
        $son = $rows->firstWhere('display_name', 'Thawng Dam');

        $this->assertSame(3, $jasuan['generation']['outer_number']);
        $this->assertSame(1, $jasuan['generation']['number']);
        $this->assertSame(4, $son['generation']['outer_number']);
        $this->assertSame(2, $son['generation']['number']);
    }

    /** @return Collection<int, Person> */
    private function chain(array $names)
    {
        $people = collect();

        foreach ($names as $i => $name) {
            $person = Person::factory()->bornExactly(1900 + $i * 25)->create([
                'clan_id' => $this->clan->id,
                'gender' => 'male',
                'display_name' => $name,
            ]);

            if ($people->isNotEmpty()) {
                $union = Union::factory()->create(['partner_1_id' => $people->last()->id]);
                app(AddChildToUnion::class)->handle($union, $person);
            }

            $people->push($person);
        }

        return $people;
    }
}
