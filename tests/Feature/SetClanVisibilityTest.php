<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Person;
use App\Models\Tribe;
use App\Services\Graph\GraphVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opening a clan's records to the clan.
 *
 * Every person starts at `family`, so a clan where nobody is above that is one
 * where joining it grants nothing. Raising them is a bulk change to real
 * people's privacy, which is exactly the kind of thing that must not quietly
 * overwrite an answer somebody gave for themselves.
 */
class SetClanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
    }

    public function test_it_raises_the_people_still_at_the_default(): void
    {
        $ordinary = $this->person(PrivacyLevel::Family);

        $this->artisan('archive:set-clan-visibility', [
            'clan' => $this->clan->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(PrivacyLevel::Clan, $ordinary->fresh()->privacyLevel());
    }

    public function test_it_never_overrides_somebody_who_chose_for_themselves(): void
    {
        // The one thing this command must not do. Nobody would see it happen:
        // a record quietly opened after its owner shut it is invisible from
        // every screen in the archive.
        $hidden = $this->person(PrivacyLevel::Private);
        $open = $this->person(PrivacyLevel::Public);

        $this->artisan('archive:set-clan-visibility', [
            'clan' => $this->clan->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(PrivacyLevel::Private, $hidden->fresh()->privacyLevel());
        $this->assertSame(PrivacyLevel::Public, $open->fresh()->privacyLevel());
    }

    public function test_it_leaves_another_clan_alone(): void
    {
        $other = Clan::factory()->create(['tribe_id' => $this->clan->tribe_id]);

        $theirs = Person::factory()->create([
            'tribe_id' => $this->clan->tribe_id,
            'clan_id' => $other->id,
            'privacy_level' => PrivacyLevel::Family,
        ]);

        $this->artisan('archive:set-clan-visibility', [
            'clan' => $this->clan->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(PrivacyLevel::Family, $theirs->fresh()->privacyLevel());
    }

    public function test_it_changes_nothing_without_force(): void
    {
        $person = $this->person(PrivacyLevel::Family);

        $this->artisan('archive:set-clan-visibility', ['clan' => $this->clan->id])
            ->expectsOutputToContain('Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame(PrivacyLevel::Family, $person->fresh()->privacyLevel());
    }

    public function test_the_cached_trees_are_not_served_under_the_old_answer(): void
    {
        $this->person(PrivacyLevel::Family);

        $before = app(GraphVersion::class)->current($this->clan->tribe_id);

        $this->artisan('archive:set-clan-visibility', [
            'clan' => $this->clan->id,
            '--force' => true,
        ])->assertSuccessful();

        // A mass update fires no observer, and privacy_level is one of the
        // fields a tree card is drawn from.
        $this->assertGreaterThan(
            $before,
            app(GraphVersion::class)->current($this->clan->tribe_id),
        );
    }

    private function person(PrivacyLevel $level): Person
    {
        return Person::factory()->create([
            'tribe_id' => $this->clan->tribe_id,
            'clan_id' => $this->clan->id,
            'privacy_level' => $level,
        ]);
    }
}
