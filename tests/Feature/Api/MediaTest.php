<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Media;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Media\MediaUrlResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photographs of a family.
 *
 * More identifying than a birth year, so the living-person mask governs them
 * the same way it governs a life story, and a private one is never handed out
 * as a URL that keeps working.
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create(['default_privacy_level' => PrivacyLevel::Tribe]);
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
        $this->branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
        ]);
    }

    private function member(string $role = 'contributor'): User
    {
        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')
                ->where('scopeable_id', $this->tribe->id)
                ->firstOrFail()->id,
            'status' => MembershipStatus::Active,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function person(): Person
    {
        // deceased(), not is_living => false: the death columns are derived
        // and cannot be set directly, and the media mask is closed for anyone
        // the resolver still considers living — which is what an unset death
        // date means, whatever the flag says.
        return Person::factory()->deceased(1998)->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Tribe,
            'verification_status' => VerificationStatus::Verified,
        ]);
    }

    public function test_a_photograph_is_stored_and_listed_against_the_person(): void
    {
        Storage::fake('r2');
        $person = $this->person();

        $this->actingAs($this->member())
            ->postJson(route('api.v1.media.store'), [
                'file' => UploadedFile::fake()->image('grandfather.jpg', 800, 600),
                'person_ulid' => $person->ulid,
            ])
            ->assertCreated();

        $media = Media::firstOrFail();
        Storage::disk('r2')->assertExists($media->path);

        $this->actingAs($this->member())
            ->getJson(route('api.v1.people.media', $person))
            ->assertOk()
            ->assertJsonPath('meta.count', 1);
    }

    public function test_an_upload_is_private_unless_it_says_otherwise(): void
    {
        Storage::fake('r2');
        $person = $this->person();

        $this->actingAs($this->member())
            ->postJson(route('api.v1.media.store'), [
                'file' => UploadedFile::fake()->image('family.jpg'),
                'person_ulid' => $person->ulid,
            ])
            ->assertCreated();

        // The default decides what happens to every photograph nobody thought
        // about, which is most of them.
        $this->assertTrue(Media::firstOrFail()->is_private);
    }

    public function test_a_private_photograph_is_never_given_a_permanent_public_url(): void
    {
        Storage::fake('r2');
        $person = $this->person();

        $media = Media::create([
            'mediable_type' => $person->getMorphClass(),
            'mediable_id' => $person->getKey(),
            'disk' => 'r2',
            'path' => 'media/1/ab/abc.jpg',
            'original_filename' => 'abc.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'is_private' => true,
        ]);

        $url = app(MediaUrlResolver::class)->url($media);

        // Either a signed link or nothing at all. The one thing it must never
        // be is the plain public-domain path, which would never expire.
        if ($url !== null) {
            $this->assertTrue(
                str_contains($url, 'Signature') || str_contains($url, 'expiration'),
                'a private photograph was given a link with no expiry',
            );
        }

        $this->assertNotSame(
            Storage::disk('r2')->url($media->path),
            $url,
            'a private photograph was handed out as its permanent public URL',
        );
    }

    public function test_a_living_persons_photographs_are_withheld_from_a_stranger(): void
    {
        Storage::fake('r2');

        // Same tribe, same privacy level — the only difference is that this
        // person is alive. A face is more identifying than a birth year, and
        // the mask closes for exactly that reason.
        // living()->bornExactly(): the bare factory picks a birth year
        // between 1900 and 1995, and anyone born before about 1916 is
        // *presumed* deceased against max_age of 110 — which made this test
        // pass or fail depending on the dice.
        $living = Person::factory()->living()->bornExactly(1990)->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'privacy_level' => PrivacyLevel::Tribe,
            'verification_status' => VerificationStatus::Verified,
        ]);

        Media::create([
            'mediable_type' => $living->getMorphClass(),
            'mediable_id' => $living->getKey(),
            'disk' => 'r2',
            'path' => 'media/1/cd/cde.jpg',
            'original_filename' => 'cde.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 2048,
            'checksum_sha256' => str_repeat('b', 64),
        ]);

        $this->actingAs($this->member())
            ->getJson(route('api.v1.people.media', $living))
            ->assertOk()
            ->assertJsonPath('meta.withheld', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        Storage::fake('r2');
        $person = $this->person();

        $this->actingAs($this->member())
            ->postJson(route('api.v1.media.store'), [
                // Named .jpg, but the bytes say otherwise — which is the whole
                // reason the rule checks content and not the extension.
                'file' => UploadedFile::fake()->create('payload.jpg', 8, 'application/x-php'),
                'person_ulid' => $person->ulid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->assertSame(0, Media::count());
    }

    public function test_the_same_photograph_twice_is_one_object(): void
    {
        Storage::fake('r2');
        $person = $this->person();
        $member = $this->member();

        $file = UploadedFile::fake()->image('shared.jpg', 400, 400);
        $bytes = file_get_contents($file->getRealPath());

        foreach ([1, 2] as $_) {
            $copy = UploadedFile::fake()->createWithContent('shared.jpg', $bytes);
            $this->actingAs($member)
                ->postJson(route('api.v1.media.store'), [
                    'file' => $copy,
                    'person_ulid' => $person->ulid,
                ])
                ->assertCreated();
        }

        // Two rows, because two people vouched for it — but one stored object,
        // because the path is the checksum.
        $this->assertSame(2, Media::count());
        $this->assertCount(1, Media::query()->distinct()->pluck('path'));
    }
}
