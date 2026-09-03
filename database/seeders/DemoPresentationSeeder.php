<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Access\AssignScopedRole;
use App\Actions\Access\RequestMembership;
use App\Actions\Claims\SubmitProfileClaim;
use App\Actions\Notifications\NotifyMembershipReviewers;
use App\Actions\Notifications\NotifyReviewers;
use App\Actions\Verification\SubmitChangeRequest;
use App\Enums\Certainty;
use App\Enums\ChangeRequestOperation;
use App\Enums\ChildRelationshipType;
use App\Enums\Gender;
use App\Enums\MembershipStatus;
use App\Enums\PersonNameType;
use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\SourceType;
use App\Enums\StoryType;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Clan;
use App\Models\DuplicateCandidate;
use App\Models\FamilyBranch;
use App\Models\Generation;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonName;
use App\Models\Relationship;
use App\Models\Scope;
use App\Models\Source;
use App\Models\Story;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\UnionChild;
use App\Models\User;
use App\Support\DemoCredentials;
use App\Support\UncertainDateParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Spatie\Permission\Models\Role;

/**
 * A presentable, self-contained tribe for showing this app to other people.
 *
 * Unlike DemoTribeSeeder, this is meant to run in production. It builds its
 * own tribe rather than adding to a real one, so it can be shown to a live
 * audience and later deleted — soft-deleted, like everything else in this
 * app — without touching anyone's actual family. Everything is idempotent, so
 * running it again before a second demo does not duplicate the tribe, only
 * refreshes the pending items that a first run would already have resolved.
 *
 * It deliberately walks through the real Actions for anything meant to be the
 * point of the demo — a membership request, a proposed edit, a profile claim —
 * rather than inserting rows directly, so the notification bell in Filament
 * genuinely lights up during the presentation instead of a row existing that
 * nothing ever announced.
 */
class DemoPresentationSeeder extends Seeder
{
    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    /** @var array<string, Person> */
    private array $people = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedOrganisation();
            $this->seedFamily();
            $this->seedNearDuplicate();
            $this->seedChronicle();
            $this->seedAccounts();
        });

        $this->command?->info(sprintf(
            'Demo presentation ready: %d people in "%s". Sign in at %s with:',
            Person::count() - $this->peopleOutsideDemo(),
            $this->tribe->name,
            config('app.url'),
        ));
        $this->command?->info('  '.DemoCredentials::ADMIN_EMAIL.' — administers the demo tribe');
        $this->command?->info('  '.DemoCredentials::VIEWER_EMAIL.' — an existing member');
        $this->command?->info('  '.DemoCredentials::APPLICANT_EMAIL.' — has just asked to join');
        $this->command?->info('  password for all three: '.DemoCredentials::PASSWORD);
    }

    private function peopleOutsideDemo(): int
    {
        return Person::where('tribe_id', '!=', $this->tribe->id ?? 0)->count();
    }

    // ── organisation ─────────────────────────────────────────────────────

    private function seedOrganisation(): void
    {
        $this->tribe = Tribe::firstOrCreate(
            ['slug' => 'whitfield-family-demo'],
            [
                'name' => 'Whitfield Family (Demo)',
                'short_name' => 'Whitfield',
                'description' => 'A fictional family, built for showing this app to people — not a real archive.',
                'default_privacy_level' => PrivacyLevel::Tribe,
                'status' => RecordStatus::Active,
            ],
        );

        $this->clan = Clan::firstOrCreate(
            ['tribe_id' => $this->tribe->id, 'slug' => 'whitfield'],
            ['name' => 'Whitfield', 'depth' => 0, 'level_label' => 'Family'],
        );

        $this->branch = FamilyBranch::firstOrCreate(
            ['tribe_id' => $this->tribe->id, 'slug' => 'whitfield-main'],
            [
                'clan_id' => $this->clan->id,
                'name' => 'Whitfield',
                'description' => 'The whole demo family — small enough not to need a second branch.',
            ],
        );

        foreach (range(1, 3) as $n) {
            Generation::firstOrCreate(
                ['tribe_id' => $this->tribe->id, 'clan_id' => null, 'generation_number' => $n],
                ['generation_name' => $this->ordinal($n).' Generation'],
            );
        }
    }

    // ── the family itself ────────────────────────────────────────────────

    private function seedFamily(): void
    {
        $edward = $this->person('Edward Whitfield', Gender::Male, '1920', '1995', generation: 1, verified: true);
        $margaret = $this->person('Margaret Whitfield', Gender::Female, '1922', '2001', generation: 1, verified: true);
        $edward->forceFill([
            'biography' => 'Edward ran the family farm outside Ashbourne for forty years, and by every '
                .'account was more patient with the animals than with his sons. He kept a diary of the '
                .'weather every day from 1952 until his death, which nobody in the family has yet found the '
                .'time to read in full.',
        ])->save();
        $this->branch->forceFill(['ancestor_person_id' => $edward->id])->save();
        $this->marry($edward, $margaret, 1943, UnionStatus::Widowed);
        $founders = $this->union('Edward Whitfield', 'Margaret Whitfield');

        $robert = $this->person('Robert Whitfield', Gender::Male, '1945', '2018', generation: 2);
        $patricia = $this->person('Patricia Cole', Gender::Female, '1948', null, generation: 2);
        $this->addChild($founders, $robert, 1);
        $this->addChild($founders, $patricia, 2);

        $susan = $this->person('Susan Whitfield', Gender::Female, '1948', null, generation: 2);
        $james = $this->person('James Cole', Gender::Male, '1946', '2010', generation: 2);
        $this->marry($robert, $susan, 1970);
        $this->marry($patricia, $james, 1972, UnionStatus::Widowed);

        $robertUnion = $this->union('Robert Whitfield', 'Susan Whitfield');
        $coleUnion = $this->union('Patricia Cole', 'James Cole');

        $daniel = $this->person('Daniel Whitfield', Gender::Male, '1972', null, generation: 3, verified: true);
        $daniel->forceFill([
            'biography' => 'Daniel took over the farmhouse after Robert, though the land itself was sold in '
                .'the nineties. He teaches secondary-school history, which everyone agrees explains a lot.',
        ])->save();
        $emily = $this->person('Emily Whitfield', Gender::Female, '1975', null, generation: 3);
        $michael = $this->person('Michael Cole', Gender::Male, '1974', null, generation: 3);

        $this->addChild($robertUnion, $daniel, 1);
        $this->addChild($robertUnion, $emily, 2);
        $this->addChild($coleUnion, $michael, 1);

        $laura = $this->person('Laura Whitfield', Gender::Female, '1974', null, generation: 3);
        $this->marry($daniel, $laura, 2005);
        $danielUnion = $this->union('Daniel Whitfield', 'Laura Whitfield');

        $sophie = $this->person('Sophie Whitfield', Gender::Female, '2008', null, generation: 3);
        $oliver = $this->person('Oliver Whitfield', Gender::Male, '2011', null, generation: 3);
        $this->addChild($danielUnion, $sophie, 1);
        $this->addChild($danielUnion, $oliver, 2);
    }

    /**
     * The same grandfather, entered a second time with a shortened name and a
     * slightly different year — left unmerged on purpose, exactly like
     * DemoTribeSeeder's, so the duplicate-review screen has something real
     * rather than an empty state.
     */
    private function seedNearDuplicate(): void
    {
        $rob = $this->person('Rob Whitfield', Gender::Male, 'abt. 1946', '2018', generation: 2);

        DuplicateCandidate::firstOrCreate(
            [
                'person_a_id' => min($this->people['Robert Whitfield']->id, $rob->id),
                'person_b_id' => max($this->people['Robert Whitfield']->id, $rob->id),
            ],
            [
                'score' => 0.870,
                'signals' => [
                    'name_similarity' => 0.90,
                    'birth_year_within_2' => true,
                    'death_year_match' => true,
                    'shared_family_branch' => true,
                ],
                'status' => 'open',
            ],
        );
    }

    private function seedChronicle(): void
    {
        $daniel = $this->people['Daniel Whitfield'];

        $timeline = [
            ['employment', 'Started teaching at Ashbourne Grammar', 1998],
            ['migration', 'Moved into the family farmhouse', 2001],
        ];

        foreach ($timeline as [$slug, $title, $year]) {
            $parsed = UncertainDateParser::parse((string) $year);

            PersonEvent::firstOrCreate(
                [
                    'person_id' => $daniel->id,
                    'event_type_id' => DB::table('event_types')->where('slug', $slug)->value('id'),
                    'title' => $title,
                ],
                [
                    'event_date' => $parsed['date'],
                    'event_date_precision' => $parsed['precision'],
                    'event_date_text' => $parsed['text'],
                    'event_year' => $parsed['year'],
                    'verification_status' => VerificationStatus::Unverified,
                ],
            );
        }

        $source = Source::firstOrCreate(
            ['title' => 'Ashbourne parish birth register, 1918–1930'],
            [
                'source_type' => SourceType::ChurchRecord,
                'description' => 'A fictional register, for demonstrating a sourced fact.',
                'repository' => 'Ashbourne Parish Church (demo)',
                'publication_year' => 1930,
                'tribe_id' => $this->tribe->id,
            ],
        );

        DB::table('citations')->insertOrIgnore([
            'source_id' => $source->id,
            'citable_type' => Person::class,
            'citable_id' => $this->people['Edward Whitfield']->id,
            'field' => 'birth_date',
            'confidence' => Certainty::Proven->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── accounts, and the things that make the demo interactive ────────────

    private function seedAccounts(): void
    {
        $admin = $this->demoUser(DemoCredentials::ADMIN_EMAIL, 'Demo Admin');
        $viewer = $this->demoUser(DemoCredentials::VIEWER_EMAIL, 'Demo Viewer');
        $applicant = $this->demoUser(DemoCredentials::APPLICANT_EMAIL, 'Demo Applicant');

        $scope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->firstOrFail();

        // The presenter's own super admin joins too — NotifyMembershipReviewers
        // only notifies active members of the scope, is_super_admin or not, so
        // without this the live "watch the bell light up" moment notifies nobody.
        $this->activeMember($this->presenter(), $scope);
        $this->activeMember($admin, $scope);
        $this->activeMember($viewer, $scope);

        $tribeAdminRole = Role::where('name', 'tribe-admin')->where('guard_name', 'web')->first();

        if ($tribeAdminRole !== null) {
            app(AssignScopedRole::class)->handle($this->presenter(), $admin, $tribeAdminRole, $scope);
        }

        // A pending membership — real Action, real notification, so opening
        // Filament during the demo shows something that just happened rather
        // than something that was always there.
        $pending = Membership::where('user_id', $applicant->id)->where('scope_id', $scope->id)->first();

        if ($pending === null || $pending->status !== MembershipStatus::Pending) {
            $membership = app(RequestMembership::class)->handle($applicant, $scope);
            app(NotifyMembershipReviewers::class)->handle($membership);
        }

        // A pending change request — proposing a biography for Emily, who does
        // not have one yet — for the review-queue screen, from the viewer.
        $emily = $this->people['Emily Whitfield'];
        $alreadyProposed = DB::table('change_requests')
            ->where('requested_by', $viewer->id)
            ->where('target_id', $emily->id)
            ->where('status', 'pending')
            ->exists();

        if (! $alreadyProposed) {
            $changeRequest = app(SubmitChangeRequest::class)->handle(
                requester: $viewer,
                operation: ChangeRequestOperation::Update,
                target: $emily,
                payload: ['biography' => 'Emily runs a small bookshop in Ashbourne and keeps threatening to '
                    .'finally write down Edward\'s diaries properly.'],
                scope: $scope,
                reason: 'Adding a short biography — I know her, this is accurate.',
            );
            app(NotifyReviewers::class)->handle($changeRequest);
        }

        // A pending profile claim — Emily is living and unclaimed, so the
        // applicant asking "this is me" is a legitimate demo of that flow.
        app(SubmitProfileClaim::class)->handle(
            $applicant,
            $emily,
            'I am Emily — this account is mine.',
            'This is my own profile.',
        );

        // A story, visible at tribe level, authored by an existing member —
        // there is no mobile screen for this yet, but the admin panel one
        // should not be empty either.
        Story::firstOrCreate(
            ['title' => 'The weather diaries', 'tribe_id' => $this->tribe->id],
            [
                'body' => 'Edward kept a note of the weather every single day from 1952 onward, in a stack '
                    .'of identical notebooks that Daniel still has in the attic. Nobody has read them start '
                    .'to finish, but the family joke is that whoever finally does will know exactly which '
                    .'summer to blame for the state of the roof.',
                'summary' => 'Forty years of one man\'s weather notes, still unread.',
                'person_id' => $edward = $this->people['Edward Whitfield']->id,
                'family_branch_id' => $this->branch->id,
                'clan_id' => $this->clan->id,
                'author_id' => $viewer->id,
                'story_type' => StoryType::Memory,
                'visibility' => PrivacyLevel::Tribe,
                'verification_status' => VerificationStatus::Unverified,
                'created_by' => $viewer->id,
            ],
        );
    }

    private function presenter(): User
    {
        return User::where('is_super_admin', true)->firstOrFail();
    }

    private function demoUser(string $email, string $name): User
    {
        // The mobile app's own sign-in screen authenticates against Firebase
        // first and only afterward exchanges the token with this server — it
        // never calls a password directly against this database. A demo
        // account with a local password hash and no matching Firebase account
        // signs in fine over curl and fails, silently to anyone not reading
        // the client log, on the one screen anyone at the presentation will
        // actually use.
        $firebaseUid = $this->ensureFirebaseAccount($email, $name);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make(DemoCredentials::PASSWORD),
            'firebase_uid' => $firebaseUid,
        ]);
        $user->email_verified_at = now();
        $user->status = UserStatus::Active;
        $user->save();

        if (! $user->hasRole('contributor')) {
            $user->assignRole('contributor');
        }

        return $user;
    }

    /**
     * Finds or creates the Firebase side of a demo account, verified so
     * ExchangeFirebaseToken's own email-linking would have found this same
     * row anyway — firebase_uid is still set directly rather than relying on
     * that, which is one less thing to go wrong on the day of the demo.
     *
     * Returns null, and warns instead of failing the whole seed, if Firebase
     * is not reachable — the same choice FcmChannel makes for a push that
     * cannot be sent. A missing credential here should not be the reason the
     * rest of the tribe fails to seed.
     */
    private function ensureFirebaseAccount(string $email, string $name): ?string
    {
        $auth = app(Auth::class);

        try {
            return $auth->getUserByEmail($email)->uid;
        } catch (UserNotFound) {
            // Falls through to creation below.
        } catch (\Throwable $e) {
            $this->command?->warn(
                "Firebase unavailable — {$email} will work over the API but not from the app's own "
                    .'sign-in screen: '.$e->getMessage(),
            );

            return null;
        }

        try {
            return $auth->createUser([
                'email' => $email,
                'email_verified' => true,
                'display_name' => $name,
                'password' => DemoCredentials::PASSWORD,
            ])->uid;
        } catch (\Throwable $e) {
            $this->command?->warn("Could not create a Firebase account for {$email}: {$e->getMessage()}");

            return null;
        }
    }

    private function activeMember(User $user, Scope $scope): void
    {
        $membership = Membership::firstOrNew(['user_id' => $user->id, 'scope_id' => $scope->id]);

        if ($membership->exists && $membership->status === MembershipStatus::Active) {
            return;
        }

        $membership->fill([
            'status' => MembershipStatus::Active,
            'approved_by' => $this->presenter()->id,
            'approved_at' => now(),
        ])->save();
    }

    // ── helpers, mirroring DemoTribeSeeder's ────────────────────────────────

    private function person(
        string $name,
        Gender $gender,
        ?string $birth,
        ?string $death,
        int $generation,
        bool $verified = false,
    ): Person {
        $parts = explode(' ', $name);
        $generationId = Generation::query()
            ->where('tribe_id', $this->tribe->id)
            ->where('generation_number', $generation)
            ->value('id');

        $person = Person::firstOrNew(['display_name' => $name, 'tribe_id' => $this->tribe->id]);

        $person->fill([
            'first_name' => $parts[0],
            'last_name' => $parts[count($parts) - 1] === $parts[0] ? null : end($parts),
            'display_name' => $name,
            'sort_name' => mb_strtolower($name),
            'gender' => $gender,
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'family_branch_id' => $this->branch->id,
            'generation_id' => $generationId,
            'privacy_level' => PrivacyLevel::Tribe,
            'verification_status' => $verified ? VerificationStatus::Verified : VerificationStatus::Unverified,
        ]);

        $person->setUncertainDate('birth', $birth);
        $person->setUncertainDate('death', $death);
        $person->is_living = $death === null && ! $person->isDeceased();
        $person->save();

        PersonName::firstOrCreate(
            ['person_id' => $person->id, 'normalized' => $this->normalize($name), 'type' => PersonNameType::Birth],
            ['name' => $name, 'is_primary' => true],
        );

        return $this->people[$name] = $person;
    }

    private function marry(
        Person $a,
        Person $b,
        ?int $year = null,
        UnionStatus $status = UnionStatus::Active,
        int $orderIndex = 1,
    ): Union {
        $parsed = UncertainDateParser::parse($year === null ? null : (string) $year);

        return Union::firstOrCreate(
            [
                'partner_1_id' => min($a->id, $b->id),
                'partner_2_id' => max($a->id, $b->id),
                'union_type' => UnionType::Marriage,
                'order_index' => $orderIndex,
            ],
            [
                'status' => $status,
                'marriage_date' => $parsed['date'],
                'marriage_date_precision' => $parsed['precision'],
                'marriage_date_text' => $parsed['text'],
                'marriage_year' => $parsed['year'],
            ],
        );
    }

    private function union(string $a, string $b): Union
    {
        $x = $this->people[$a]->id;
        $y = $this->people[$b]->id;

        return Union::where('partner_1_id', min($x, $y))
            ->where('partner_2_id', max($x, $y))
            ->firstOrFail();
    }

    private function addChild(Union $union, Person $child, int $birthOrder): void
    {
        UnionChild::firstOrCreate(
            ['union_id' => $union->id, 'person_id' => $child->id],
            ['relationship_type' => ChildRelationshipType::Biological, 'birth_order' => $birthOrder],
        );

        foreach (array_filter([$union->partner_1_id, $union->partner_2_id]) as $parentId) {
            Relationship::firstOrCreate(
                [
                    'person_id' => $parentId,
                    'related_person_id' => $child->id,
                    'relationship_type' => RelationshipType::ParentChild,
                    'relationship_subtype' => RelationshipSubtype::Biological,
                ],
                [
                    'is_biological' => true,
                    'union_id' => $union->id,
                    'certainty' => Certainty::Probable,
                ],
            );
        }
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name);
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }
}
