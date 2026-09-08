<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NadOfferingsSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve the NAD category — create it if it doesn't exist yet.
        $nad = OfferingCategory::firstOrCreate(
            ['name' => 'NAD'],
            ['description' => 'NAD+ and NAD+ combination compounded infusions', 'is_active' => true]
        );

        // Seed against every active partner so clinicians at any storefront
        // can prescribe these compounds. Filter down here if needed.
        $partnerIds = Partner::pluck('id');

        $products = [
            // ── NAD+ (Nicotinamide Adenine Dinucleotide) ────────────────────
            [
                'name'             => 'NAD+ (Nicotinamide Adenine Dinucleotide)',
                'internal_name'    => 'NAD+',
                'compound_formula' => 'Nicotinamide Adenine Dinucleotide compounded injection',
                'formulation_type' => 'injectable',
                'levels' => [
                    ['label' => 'LVL1 - 500MG',  'formula' => '100mg/mL (5mL)'],
                    ['label' => 'LVL2 - 1000MG', 'formula' => '100mg/mL (10mL)'],
                ],
            ],

            // ── NAD+/Glutathione ─────────────────────────────────────────────
            [
                'name'             => 'NAD+/Glutathione',
                'internal_name'    => 'NAD+/Glut',
                'compound_formula' => 'Nicotinamide Adenine Dinucleotide / Glutathione compounded injection',
                'formulation_type' => 'injectable',
                'levels' => [
                    ['label' => 'LVL1 - 500MG/500MG',   'formula' => '100mg/100mg/mL (5mL)'],
                    ['label' => 'LVL2 - 1000MG/1000MG', 'formula' => '100mg/100mg/mL (10mL)'],
                ],
            ],
        ];

        // Shared defaults for all NAD compounds.
        $defaults = [
            'type'                    => 'compound',
            'pharmacy_type'           => 'boothwyn',
            'dispense_unit'           => 'mL',
            'quantity'                => 5,      // LVL1 vial size; formula field carries per-level volume
            'days_supply'             => 30,
            'refills'                 => 0,
            'days_until_dispense'     => null,
            'directions'              => 'Administer intravenously or intramuscularly as directed by your provider.',
            'is_active'               => true,
            'is_controlled_substance' => false,
            'approval_status'         => 'approved',
        ];

        foreach ($partnerIds as $partnerId) {
            foreach ($products as $product) {
                $name            = $product['name'];
                $formulationType = $product['formulation_type'];

                // Backfill formulation_type on existing offerings (including soft-deleted).
                $existing = Offering::withTrashed()
                    ->where('partner_id', $partnerId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    if ($existing->formulation_type !== $formulationType) {
                        $existing->formulation_type = $formulationType;
                        $existing->saveQuietly();
                        $this->command->line("  updated  [{$name}] formulation_type={$formulationType} for partner #{$partnerId}");
                    } else {
                        $this->command->line("  skipped  [{$name}] already up-to-date for partner #{$partnerId}");
                    }
                    continue;
                }

                Offering::create(array_merge($defaults, [
                    'uuid'             => Str::uuid(),
                    'partner_id'       => $partnerId,
                    'category_id'      => $nad->id,
                    'name'             => $name,
                    'internal_name'    => $product['internal_name'],
                    'compound_formula' => $product['compound_formula'],
                    'formulation_type' => $formulationType,
                    'levels'           => $product['levels'],
                ]));

                $this->command->info("  created  [{$name}] for partner #{$partnerId}");
            }
        }

        // Catch-all: backfill any offering records not covered by the per-partner loop above.
        // This handles offerings with partner_id = NULL (global/shared) or any record
        // whose partner_id wasn't in the current Partner list at seeder run time.
        $bulkUpdated = 0;
        foreach ($products as $product) {
            $rows = Offering::withTrashed()
                ->where('name', $product['name'])
                ->whereNull('formulation_type')
                ->update(['formulation_type' => $product['formulation_type']]);
            $bulkUpdated += $rows;
        }
        if ($bulkUpdated > 0) {
            $this->command->line("  bulk-backfilled formulation_type on {$bulkUpdated} additional offering record(s).");
        }

        $this->command->info('NAD offerings seeded.');
    }
}
