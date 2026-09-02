<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Certainty;
use App\Enums\ChildRelationshipType;
use App\Enums\Gender;
use App\Enums\PersonNameType;
use App\Enums\PrivacyLevel;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\SourceType;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Enums\VerificationStatus;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Generation;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonName;
use App\Models\Place;
use App\Models\Relationship;
use App\Models\Source;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\UnionChild;
use App\Support\NameCorpus;
use App\Support\UncertainDateParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A small, realistic genealogy for local development.
 *
 * DEVELOPMENT SCAFFOLDING ONLY — gated to the local and testing environments.
 * It exists so the tree UI, the traversal CTEs and the duplicate matcher can be
 * built against realistic shapes: uncertain dates, an adoption, a second
 * marriage, a single-parent union, spelling variants and a genuine near-duplicate.
 * Nothing in the application reads it, and it never runs in production.
 */
class DemoTribeSeeder extends Seeder
{
    private Tribe $tribe;

    private Clan $clan;

    private FamilyBranch $branch;

    /** @var array<string, Person> */
    private array $people = [];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoTribeSeeder skipped: not a local environment.');

            return;
        }

        DB::transaction(function (): void {
            $this->seedOrganisation();
            $this->seedFounders();
            $this->seedSecondGeneration();
            $this->seedThirdAndFourth();
            $this->seedSpellingVariantsAndDuplicate();
            $this->seedChronicle();
        });

        $this->command?->info(sprintf(
            'Demo tribe seeded: %d people, %d unions, %d relationships.',
            Person::count(),
            Union::count(),
            Relationship::count(),
        ));
    }

    private function seedOrganisation(): void
    {
        $this->tribe = Tribe::firstOrCreate(
            ['slug' => 'zomi'],
            [
                'name' => 'Zomi',
                'native_name' => 'Zomi',
                'short_name' => 'ZMI',
                'description' => 'Demonstration tribe for local development.',
                'country_code' => 'MM',
                'region' => 'Chin State',
                'default_privacy_level' => PrivacyLevel::Tribe,
            ],
        );

        $this->clan = Clan::firstOrCreate(
            ['tribe_id' => $this->tribe->id, 'slug' => 'guite'],
            [
                'name' => 'Guite',
                'native_name' => 'Guite',
                'depth' => 0,
                'level_label' => 'Clan',
            ],
        );

        // A sub-clan, to prove hierarchy depth is data rather than schema.
        Clan::firstOrCreate(
            ['tribe_id' => $this->tribe->id, 'slug' => 'guite-tedim'],
            [
                'name' => 'Guite (Tedim)',
                'parent_clan_id' => $this->clan->id,
                'depth' => 1,
                'level_label' => 'Sub-clan',
            ],
        );

        $this->branch = FamilyBranch::firstOrCreate(
            ['tribe_id' => $this->tribe->id, 'slug' => 'kin-tun-family'],
            [
                'clan_id' => $this->clan->id,
                'name' => 'Kin Tun Family',
                'description' => 'Descendants of Kin Tun of Tedim.',
                'origin_place_id' => $this->placeId('Tedim', 'village'),
            ],
        );

        foreach (range(11, 17) as $n) {
            Generation::firstOrCreate(
                ['tribe_id' => $this->tribe->id, 'clan_id' => null, 'generation_number' => $n],
                ['generation_name' => $this->ordinal($n).' Generation'],
            );
        }
    }

    private function seedFounders(): void
    {
        $kinTun = $this->person('Kin Tun', Gender::Male, '1898', '1961', generation: 11);
        $zaVung = $this->person('Za Vung', Gender::Female, 'abt. 1902', '1979', generation: 11);

        $this->branch->forceFill(['ancestor_person_id' => $kinTun->id])->save();

        $this->marry($kinTun, $zaVung, 1920, UnionStatus::Widowed);
    }

    private function seedSecondGeneration(): void
    {
        $founders = $this->union('Kin Tun', 'Za Vung');

        $pauZam = $this->person('Pau Zam', Gender::Male, '1926', '1994', generation: 12);
        $khaiVum = $this->person('Khai Vum', Gender::Male, 'abt. 1929', '2001', generation: 12);
        // No dates at all — the fail-closed privacy case.
        $tunKhoi = $this->person('Tun Khoi', Gender::Male, null, null, generation: 12);

        $this->addChild($founders, $pauZam, 1);
        $this->addChild($founders, $khaiVum, 2);
        // Adopted, so the tree renders this edge dashed.
        $this->addChild($founders, $tunKhoi, 3, ChildRelationshipType::Adoptive);

        $khoiDim = $this->person('Khoi Dim', Gender::Female, '1930', null, generation: 12);
        $cingHau = $this->person('Cing Hau', Gender::Female, '1920s', '1998', generation: 12);

        $this->marry($pauZam, $khoiDim, 1949);
        $this->marry($khaiVum, $cingHau, 1952);

        // A second marriage, to exercise order_index and multiple unions.
        $nemCing = $this->person('Nem Cing', Gender::Female, '1935', null, generation: 12);
        $this->marry($khaiVum, $nemCing, 1971, UnionStatus::Active, orderIndex: 2);
    }

    private function seedThirdAndFourth(): void
    {
        $tunKhoi = $this->people['Tun Khoi'];

        // A single-parent union: the mother is unrecorded, which is common and
        // must not block the record.
        $singleParent = Union::create([
            'partner_1_id' => $tunKhoi->id,
            'partner_2_id' => null,
            'union_type' => UnionType::Unknown,
            'status' => UnionStatus::Unknown,
            'order_index' => 1,
        ]);

        $hauNeng = $this->person('Hau Neng', Gender::Male, '1955', null, generation: 13);
        $this->addChild($singleParent, $hauNeng, 1);

        $dimZel = $this->person('Dim Zel', Gender::Female, 'abt. 1958', null, generation: 13);
        $this->marry($hauNeng, $dimZel, 1978);
        $hauNengUnion = $this->union('Hau Neng', 'Dim Zel');

        $thawngDam = $this->person('Thawng Dam', Gender::Male, '1980', null, generation: 14, verified: true);
        $khupKhua = $this->person('Khup Khua Suan', Gender::Male, '1983', null, generation: 14);
        $nengKham = $this->person('Neng Kham', Gender::Female, '1986', null, generation: 14);

        $this->addChild($hauNengUnion, $thawngDam, 1);
        $this->addChild($hauNengUnion, $khupKhua, 2);
        $this->addChild($hauNengUnion, $nengKham, 3);

        // A fifth generation, so ancestor and descendant traversal both have
        // something to walk past the default depth.
        $ciinNiang = $this->person('Ciin Niang', Gender::Female, '1984', null, generation: 14);
        $this->marry($thawngDam, $ciinNiang, 2006);
        $thawngUnion = $this->union('Thawng Dam', 'Ciin Niang');

        foreach ([['Lian Kham', Gender::Male, '2008'], ['Man Lun', Gender::Female, '2011']] as $i => [$name, $gender, $year]) {
            $child = $this->person($name, $gender, $year, null, generation: 15);
            $this->addChild($thawngUnion, $child, $i + 1);
        }
    }

    private function seedSpellingVariantsAndDuplicate(): void
    {
        $thawngDam = $this->people['Thawng Dam'];

        foreach (NameCorpus::variantsOf('Thawng Dam') as $variant) {
            PersonName::firstOrCreate(
                [
                    'person_id' => $thawngDam->id,
                    'normalized' => $this->normalize($variant),
                    'type' => PersonNameType::Alternate,
                ],
                ['name' => $variant],
            );
        }

        PersonName::firstOrCreate(
            [
                'person_id' => $thawngDam->id,
                'normalized' => $this->normalize('ထန်ဒမ်'),
                'type' => PersonNameType::Native,
            ],
            ['name' => 'ထန်ဒမ်', 'script' => 'mymr', 'language' => 'my'],
        );

        // A genuine near-duplicate: the same ancestor entered by a second
        // contributor, with a variant spelling and a slightly different year.
        // Left unmerged on purpose so the matcher has something real to find.
        $this->person('Pau Zamm', Gender::Male, 'abt. 1927', '1994', generation: 12);
    }

    private function seedChronicle(): void
    {
        $source = Source::firstOrCreate(
            ['title' => 'Tedim church baptism register, 1918–1962'],
            [
                'source_type' => SourceType::ChurchRecord,
                'description' => 'Parish register held at Tedim; entries transcribed by hand.',
                'repository' => 'Tedim Baptist Church',
                'publication_year' => 1962,
                'tribe_id' => $this->tribe->id,
            ],
        );

        $hauNeng = $this->people['Hau Neng'];

        $timeline = [
            ['migration', 'Moved to Kalay',        1992, 'Tedim',  'Kalay'],
            ['migration', 'Moved to Kuala Lumpur', 2004, 'Kalay',  'Kuala Lumpur'],
            ['employment', 'Teacher, Tedim',       1979, null,     null],
            ['church_service', 'Deacon',           1988, null,     null],
        ];

        foreach ($timeline as [$slug, $title, $year, $from, $to]) {
            $parsed = UncertainDateParser::parse((string) $year);

            PersonEvent::firstOrCreate(
                [
                    'person_id' => $hauNeng->id,
                    'event_type_id' => DB::table('event_types')->where('slug', $slug)->value('id'),
                    'title' => $title,
                ],
                [
                    'event_date' => $parsed['date'],
                    'event_date_precision' => $parsed['precision'],
                    'event_date_text' => $parsed['text'],
                    'event_year' => $parsed['year'],
                    'from_place_id' => $from ? $this->placeId($from) : null,
                    'to_place_id' => $to ? $this->placeId($to) : null,
                    'verification_status' => VerificationStatus::Unverified,
                ],
            );
        }

        DB::table('citations')->insertOrIgnore([
            'source_id' => $source->id,
            'citable_type' => Person::class,
            'citable_id' => $this->people['Thawng Dam']->id,
            'field' => 'birth_date',
            'confidence' => Certainty::Probable->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

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

        $person = Person::firstOrNew(['display_name' => $name, 'birth_date_text' => $birth]);

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
            'birth_place_id' => $this->placeId('Tedim', 'village'),
            'privacy_level' => $death !== null ? PrivacyLevel::Tribe : PrivacyLevel::Family,
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
        UnionStatus $status = UnionStatus::Unknown,
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
                'marriage_place_id' => $this->placeId('Tedim', 'village'),
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

    /**
     * Writes the grouping row AND the parent edges — the two are only
     * meaningful together, which is why this is one method and one transaction.
     * AddChildToUnion (Phase 6) is the production version of this.
     */
    private function addChild(
        Union $union,
        Person $child,
        int $birthOrder,
        ChildRelationshipType $kind = ChildRelationshipType::Biological,
    ): void {
        UnionChild::firstOrCreate(
            ['union_id' => $union->id, 'person_id' => $child->id],
            ['relationship_type' => $kind, 'birth_order' => $birthOrder],
        );

        $subtype = match ($kind) {
            ChildRelationshipType::Adoptive => RelationshipSubtype::Adoptive,
            ChildRelationshipType::Step => RelationshipSubtype::Step,
            ChildRelationshipType::Foster => RelationshipSubtype::Foster,
            default => RelationshipSubtype::Biological,
        };

        foreach (array_filter([$union->partner_1_id, $union->partner_2_id]) as $parentId) {
            Relationship::firstOrCreate(
                [
                    'person_id' => $parentId,
                    'related_person_id' => $child->id,
                    'relationship_type' => RelationshipType::ParentChild,
                    'relationship_subtype' => $subtype,
                ],
                [
                    'is_biological' => $subtype === RelationshipSubtype::Biological,
                    'union_id' => $union->id,
                    'certainty' => Certainty::Probable,
                ],
            );
        }

        // children_count is maintained by UnionChildObserver.
    }

    private function placeId(string $name, ?string $type = null): ?int
    {
        return Place::query()
            ->where('name', $name)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->value('id');
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
