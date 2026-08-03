<?php

namespace Database\Seeders;

use App\Models\OfferingCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard offering category taxonomy.
 *
 * Categories gate clinician eligibility: a doctor only receives cases for
 * categories they have been opted into. A fresh deploy with no categories
 * means no cases can route to anyone, which looks like a broken system.
 *
 * IDEMPOTENT — firstOrCreate on name. A category an admin has already
 * created (including one they intentionally deactivated) is left exactly
 * as-is; only missing categories are added.
 *
 * Must run BEFORE GlpOfferingsSeeder, which looks up the GLP category by
 * name to assign it to offerings.
 *
 * Depends on: nothing.
 */
class OfferingCategoriesSeeder extends Seeder
{
    private const CATEGORIES = [
        [
            'name'        => 'GLP',
            'description' => 'GLP-1 and GLP-1/GIP compounded medications (Semaglutide, Tirzepatide).',
        ],
        [
            'name'        => 'NAD',
            'description' => 'NAD+ and related metabolic support compounds.',
        ],
        [
            'name'        => 'Anti-Aging',
            'description' => 'Anti-aging peptides, hormones, and longevity compounds.',
        ],
        [
            'name'        => 'Peptides',
            'description' => 'Therapeutic peptide compounds not covered by other categories.',
        ],
        [
            'name'        => 'ED',
            'description' => 'Erectile dysfunction medications and related compounds.',
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::CATEGORIES as $cat) {
            $result = OfferingCategory::firstOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description'], 'is_active' => true]
            );

            $result->wasRecentlyCreated ? $created++ : $skipped++;

            $this->command->line(
                ($result->wasRecentlyCreated ? '  created' : '  skipped')
                . "  [{$cat['name']}]"
                . ($result->wasRecentlyCreated ? '' : ' already exists')
            );
        }

        $this->command->info("OfferingCategoriesSeeder: {$created} created, {$skipped} already existed.");
    }
}
