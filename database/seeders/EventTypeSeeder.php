<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\EventType;
use Illuminate\Database\Seeder;

/**
 * System-wide event types.
 *
 * These are rows, not an enum, precisely so a tribe can add its own — a naming
 * ceremony, a feast of merit, a clan installation — without a migration.
 * Idempotent.
 */
class EventTypeSeeder extends Seeder
{
    /** @var list<array{0:string,1:string,2:EventCategory,3:int}> */
    private const TYPES = [
        ['birth',            'Birth',              EventCategory::Vital,     10],
        ['baptism',          'Baptism',            EventCategory::Religious, 20],
        ['naming',           'Naming ceremony',    EventCategory::Family,    25],
        ['education',        'Education',          EventCategory::Education, 30],
        ['graduation',       'Graduation',         EventCategory::Education, 35],
        ['employment',       'Employment',         EventCategory::Work,      40],
        ['migration',        'Migration',          EventCategory::Migration, 45],
        ['residence',        'Residence',          EventCategory::Migration, 46],
        ['marriage',         'Marriage',           EventCategory::Family,    50],
        ['divorce',          'Divorce',            EventCategory::Family,    55],
        ['church_service',   'Church service',     EventCategory::Religious, 60],
        ['ordination',       'Ordination',         EventCategory::Religious, 62],
        ['military_service', 'Military service',   EventCategory::Military,  65],
        ['leadership',       'Leadership role',    EventCategory::Civic,     70],
        ['award',            'Award or honour',    EventCategory::Civic,     75],
        ['illness',          'Illness',            EventCategory::Vital,     80],
        ['death',            'Death',              EventCategory::Vital,     90],
        ['burial',           'Burial',             EventCategory::Vital,     92],
        ['memorial',         'Memorial',           EventCategory::Religious, 94],
        ['other',            'Other',              EventCategory::Other,     99],
    ];

    public function run(): void
    {
        foreach (self::TYPES as [$slug, $label, $category, $order]) {
            EventType::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $label,
                    'category' => $category,
                    'is_system' => true,
                    'sort_order' => $order,
                    'tribe_id' => null,
                ],
            );
        }

        $this->command?->info(sprintf('Seeded %d system event types.', count(self::TYPES)));
    }
}
