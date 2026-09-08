<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Correcting somebody, and being able to see that it happened.
 */
class CorrectingAPersonTest extends TestCase
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
    public function correcting_a_name_part_changes_the_name_on_every_screen(): void
    {
        $person = Person::factory()->bornExactly(1950)->create([
            'first_name' => 'Cing',
            'last_name' => 'Man',
        ]);

        $this->assertSame('Cing Man', $person->display_name);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), [
                'first_name' => 'Ciin',
            ])
            ->assertSuccessful();

        // Every screen reads display_name. Writing the part and leaving the
        // displayed name alone is an edit that saves, reports success and
        // looks to the person who made it like it failed.
        $this->assertSame('Ciin Man', $person->refresh()->display_name);
    }

    #[Test]
    public function a_name_written_out_in_full_is_not_rebuilt_from_the_parts(): void
    {
        $person = Person::factory()->bornExactly(1950)->create([
            'first_name' => 'Pau',
            'display_name' => 'PAU KHUA NEM (KHUPMU)',
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), [
                'display_name' => 'PAU KHUA NEM (KHUPMU II)',
                'first_name' => 'Pau',
            ])
            ->assertSuccessful();

        // Names here do not split into a first and a last. Somebody who writes
        // one out in full has given the answer, and composing "Pau" over it
        // would be the archive overwriting the thing it exists to record.
        $this->assertSame(
            'PAU KHUA NEM (KHUPMU II)',
            $person->refresh()->display_name,
        );
    }

    #[Test]
    public function a_field_can_be_emptied_again(): void
    {
        $person = Person::factory()->bornExactly(1950)->create([
            'display_name' => 'Cing Man',
            'nickname' => 'Cingi',
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), [
                'nickname' => null,
            ])
            ->assertSuccessful();

        // Correcting a mistake includes taking something back out. A form that
        // only ever sends what is filled in cannot undo its own typo.
        $this->assertNull($person->refresh()->nickname);
    }

    #[Test]
    public function gender_can_be_corrected(): void
    {
        $person = Person::factory()->bornExactly(1950)->create(['gender' => 'unknown']);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), [
                'gender' => 'female',
            ])
            ->assertSuccessful();

        // It decides whether the chart calls her a daughter or a son, and
        // most imported records arrive with it unset.
        $this->assertSame('female', $person->refresh()->gender->value);
    }
}
