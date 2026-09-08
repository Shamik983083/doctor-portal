<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Peptide offerings (BPC-157, BPC-157/TB-500, Tesamorelin) for every
 * active partner under the "Peptides" category.
 *
 * IDEMPOTENT — skips offerings that already exist for a partner/name pair;
 * only backfills formulation_type if missing on an existing record.
 *
 * Depends on: OfferingCategoriesSeeder (Peptides category must exist first).
 * Run PartnerProductPlanSeeder after this to create plan rows + offering_partner pivot.
 */
class PeptideOfferingsSeeder extends Seeder
{
    public function run(): void
    {
        $peptides = OfferingCategory::firstOrCreate(
            ['name' => 'Peptides'],
            ['description' => 'Therapeutic peptide compounds not covered by other categories.', 'is_active' => true]
        );

        $partnerIds = Partner::pluck('id');

        $products = [
            // ── BPC-157 ──────────────────────────────────────────────────────
            [
                'name'             => 'BPC-157',
                'internal_name'    => 'BPC-157',
                'compound_formula' => 'BPC-157 compounded injection',
                'formulation_type' => 'injectable',
                'levels' => [
                    ['label' => 'LVL1 - 15MG', 'formula' => '3mg/mL (5mL)'],
                ],
            ],

            // ── BPC-157 / TB-500 ─────────────────────────────────────────────
            [
                'name'             => 'BPC-157/TB-500',
                'internal_name'    => 'BPC-157/TB-500',
                'compound_formula' => 'BPC-157 / TB-500 compounded injection',
                'formulation_type' => 'injectable',
                'levels' => [
                    ['label' => 'LVL1 - 15MG/15MG', 'formula' => '3mg/3mg/mL (5mL)'],
                ],
            ],

            // ── Tesamorelin ──────────────────────────────────────────────────
            [
                'name'             => 'Tesamorelin',
                'internal_name'    => 'Tesamorelin',
                'compound_formula' => 'Tesamorelin compounded injection',
                'formulation_type' => 'injectable',
                'levels' => [
                    ['label' => 'LVL1 - 10MG', 'formula' => '2mg/mL (5mL)'],
                ],
            ],
        ];

        $defaults = [
            'type'                    => 'compound',
            'pharmacy_type'           => 'boothwyn',
            'dispense_unit'           => 'mL',
            'quantity'                => 5,
            'days_supply'             => 30,
            'refills'                 => 0,
            'days_until_dispense'     => null,
            'directions'              => 'Inject subcutaneously as directed per protocol.',
            'is_active'               => true,
            'is_controlled_substance' => false,
            'approval_status'         => 'approved',
        ];

        foreach ($partnerIds as $partnerId) {
            foreach ($products as $product) {
                $name            = $product['name'];
                $formulationType = $product['formulation_type'];

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
                    'category_id'      => $peptides->id,
                    'name'             => $name,
                    'internal_name'    => $product['internal_name'],
                    'compound_formula' => $product['compound_formula'],
                    'formulation_type' => $formulationType,
                    'levels'           => $product['levels'],
                ]));

                $this->command->info("  created  [{$name}] for partner #{$partnerId}");
            }
        }

        // Catch-all: backfill formulation_type on any existing records missed above.
        $bulkUpdated = 0;
        foreach ($products as $product) {
            $rows = Offering::withTrashed()
                ->where('name', $product['name'])
                ->whereNull('formulation_type')
                ->update(['formulation_type' => $product['formulation_type']]);
            $bulkUpdated += $rows;
        }
        if ($bulkUpdated > 0) {
            $this->command->line("  bulk-backfilled formulation_type on {$bulkUpdated} additional record(s).");
        }

        $this->command->info('Peptide offerings seeded.');
    }
}
