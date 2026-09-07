<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Actions\Genealogy\AddChildToUnion;
use App\Enums\Gender;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Seven sons and two daughters, thirty-one grandchildren" — how a family
 * describes itself, and the one thing a chart cannot be counted for by hand
 * once it runs past a page.
 */
class DescendantCensusTest extends TestCase
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
    public function it_counts_each_remove_separately_and_by_sex(): void
    {
        $father = $this->person();

        $sons = [$this->child($father, Gender::Male), $this->child($father, Gender::Male)];
        $this->child($father, Gender::Female);

        // Two grandchildren under the first son, one under the second.
        $this->child($sons[0], Gender::Male);
        $this->child($sons[0], Gender::Female);
        $this->child($sons[1], Gender::Male);

        $summary = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.summary', $father))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            [
                ['depth' => 1, 'male' => 2, 'female' => 1, 'unknown' => 0, 'total' => 3],
                ['depth' => 2, 'male' => 2, 'female' => 1, 'unknown' => 0, 'total' => 3],
            ],
            $summary['generations'],
        );

        $this->assertSame(6, $summary['total']);
    }

    #[Test]
    public function nobody_descends_from_themselves(): void
    {
        $person = $this->person();

        $summary = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.summary', $person))
            ->assertOk()
            ->json('data');

        $this->assertSame([], $summary['generations']);
        $this->assertSame(0, $summary['total']);
    }

    #[Test]
    public function it_counts_the_graph_rather_than_a_fetched_window(): void
    {
        // Six generations, deeper than any tree request would return by
        // default. A chart drawn three deep must not caption itself as a
        // family three generations large.
        $person = $this->person();
        $line = $person;

        for ($i = 0; $i < 6; $i++) {
            $line = $this->child($line, Gender::Male);
        }

        $summary = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.summary', $person))
            ->assertOk()
            ->json('data');

        $this->assertCount(6, $summary['generations']);
        $this->assertSame(6, $summary['total']);
        $this->assertSame(6, $summary['generations'][5]['depth']);
    }

    private function person(): Person
    {
        return Person::factory()->bornExactly(1900)->create();
    }

    private function child(Person $parent, Gender $gender): Person
    {
        $union = Union::factory()->create(['partner_1_id' => $parent->id]);
        $child = Person::factory()->bornExactly(1930)->create(['gender' => $gender]);

        app(AddChildToUnion::class)->handle($union, $child);

        return $child;
    }
}
