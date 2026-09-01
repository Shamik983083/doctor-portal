<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GlpOfferingsSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve the GLP category — create it if it doesn't exist yet.
        $glp = OfferingCategory::firstOrCreate(
            ['name' => 'GLP'],
            ['description' => 'GLP-1 and GLP-1/GIP compounded medications', 'is_active' => true]
        );

        // Seed against every active partner so clinicians at any storefront
        // can prescribe these compounds. Filter down here if needed.
        $partnerIds = Partner::pluck('id');

        $products = [
            // ── Semaglutide / Cyanocobalamin (B12) ──────────────────────────
            [
                'name'             => 'Semaglutide/Cyanocobalamin (B12)',
                'internal_name'    => 'Sema/B12',
                'compound_formula' => 'Semaglutide / Cyanocobalamin (B12) compounded injection',
                'levels' => [
                    ['label' => 'LVL1 - 1MG (0.25mg/wk)',   'formula' => '0.25mg/0.5mg/0.5mL (2mL)'],
                    ['label' => 'LVL2 - 2MG (0.5mg/wk)',    'formula' => '0.5mg/0.5mg/0.5mL (2mL)'],
                    ['label' => 'LVL3 - 4MG (1mg/wk)',      'formula' => '1mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL4 - 6.8MG (1.7mg/wk)', 'formula' => '1.7mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL5 - 10MG (2.5mg/wk)',   'formula' => '2.5mg/1mg/0.5mL (2mL)'],
                ],
            ],

            // ── Semaglutide / Pyridoxine (B6) ───────────────────────────────
            [
                'name'             => 'Semaglutide/Pyridoxine (B6)',
                'internal_name'    => 'Sema/B6',
                'compound_formula' => 'Semaglutide / Pyridoxine (B6) compounded injection',
                'levels' => [
                    ['label' => 'LVL1 - 1MG (0.25mg/wk)',   'formula' => '0.25mg/0.5mg/0.5mL (2mL)'],
                    ['label' => 'LVL2 - 2MG (0.5mg/wk)',    'formula' => '0.5mg/0.5mg/0.5mL (2mL)'],
                    ['label' => 'LVL3 - 4MG (1mg/wk)',      'formula' => '1mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL4 - 6.8MG (1.7mg/wk)', 'formula' => '1.7mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL5 - 10MG (2.5mg/wk)',   'formula' => '2.5mg/1mg/0.5mL (2mL)'],
                ],
            ],

            // ── Tirzepatide / Cyanocobalamin (B12) ──────────────────────────
            [
                'name'             => 'Tirzepatide/Cyanocobalamin (B12)',
                'internal_name'    => 'Tirz/B12',
                'compound_formula' => 'Tirzepatide / Cyanocobalamin (B12) compounded injection',
                'levels' => [
                    ['label' => 'LVL1 - 10MG (2.5mg/wk)',   'formula' => '2.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL2 - 20MG (5mg/wk)',     'formula' => '5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL3 - 30MG (7.5mg/wk)',   'formula' => '7.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL4 - 40MG (10mg/wk)',    'formula' => '10mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL5 - 50MG (12.5mg/wk)',  'formula' => '12.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL6 - 60MG (15mg/wk)',    'formula' => '15mg/1mg/0.5mL (2mL)'],
                ],
            ],

            // ── Tirzepatide / Pyridoxine (B6) ───────────────────────────────
            [
                'name'             => 'Tirzepatide/Pyridoxine (B6)',
                'internal_name'    => 'Tirz/B6',
                'compound_formula' => 'Tirzepatide / Pyridoxine (B6) compounded injection',
                'levels' => [
                    ['label' => 'LVL1 - 10MG (2.5mg/wk)',   'formula' => '2.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL2 - 20MG (5mg/wk)',     'formula' => '5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL3 - 30MG (7.5mg/wk)',   'formula' => '7.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL4 - 40MG (10mg/wk)',    'formula' => '10mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL5 - 50MG (12.5mg/wk)',  'formula' => '12.5mg/1mg/0.5mL (2mL)'],
                    ['label' => 'LVL6 - 60MG (15mg/wk)',    'formula' => '15mg/1mg/0.5mL (2mL)'],
                ],
            ],

            // ── Semaglutide / Tirzepatide combination ────────────────────────
            [
                'name'             => 'Semaglutide/Tirzepatide',
                'internal_name'    => 'Sema/Tirz',
                'compound_formula' => 'Semaglutide / Tirzepatide compounded injection',
                'levels' => [
                    ['label' => 'LVL1 - 0.8MG/8MG',   'formula' => '0.2mg/2mg/0.5mL (2mL)'],
                    ['label' => 'LVL2 - 1.6MG/16MG',  'formula' => '0.4mg/4mg/0.5mL (2mL)'],
                    ['label' => 'LVL3 - 2.4MG/24MG',  'formula' => '0.6mg/6mg/0.5mL (2mL)'],
                    ['label' => 'LVL4 - 3.2MG/32MG',  'formula' => '0.8mg/8mg/0.5mL (2mL)'],
                    ['label' => 'LVL5 - 4MG/40MG',    'formula' => '1mg/10mg/0.5mL (2mL)'],
                    ['label' => 'LVL6 - 6MG/48MG',    'formula' => '1.5mg/12mg/0.5mL (2mL)'],
                ],
            ],

            // ── Semaglutide Tablet (SNAC) ────────────────────────────────────
            [
                'name'             => 'Semaglutide Tablet (SNAC)',
                'internal_name'    => 'Sema SNAC',
                'compound_formula' => 'Semaglutide / SNAC oral capsule',
                'dispense_unit'    => 'capsule',
                'quantity'         => 30,
                'days_supply'      => 30,
                'levels' => [
                    ['label' => 'LVL1', 'formula' => '1.7mg/cap (30ct)'],
                    ['label' => 'LVL2', 'formula' => '4.8mg/cap (30ct)'],
                    ['label' => 'LVL3', 'formula' => '9.9mg/cap (30ct)'],
                    ['label' => 'LVL4', 'formula' => '27mg/cap (30ct)'],
                ],
            ],
        ];

        // Shared defaults for all injectable GLP compounds.
        $injectableDefaults = [
            'type'          => 'compound',
            'pharmacy_type' => 'boothwyn',
            'dispense_unit' => 'mL',
            'quantity'      => 2,      // 2 mL vial
            'days_supply'   => 30,
            'refills'       => 0,
            'days_until_dispense' => null,
            'directions'    => 'Inject subcutaneously once weekly as directed. Titrate per protocol.',
            'is_active'     => true,
            'is_controlled_substance' => false,
            'approval_status' => 'approved',
        ];

        foreach ($partnerIds as $partnerId) {
            foreach ($products as $product) {
                $name = $product['name'];

                // Skip if this offering already exists for this partner.
                $exists = Offering::withTrashed()
                    ->where('partner_id', $partnerId)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $this->command->line("  skipped  [{$name}] already exists for partner #{$partnerId}");
                    continue;
                }

                $attrs = array_merge($injectableDefaults, [
                    'uuid'          => Str::uuid(),
                    'partner_id'    => $partnerId,
                    'category_id'   => $glp->id,
                    'name'          => $name,
                    'internal_name' => $product['internal_name'],
                    'compound_formula' => $product['compound_formula'],
                    'levels'        => $product['levels'],
                ]);

                // Product-level overrides (e.g. SNAC tablet has different unit/qty).
                foreach (['dispense_unit', 'quantity', 'days_supply'] as $override) {
                    if (isset($product[$override])) {
                        $attrs[$override] = $product[$override];
                    }
                }

                Offering::create($attrs);

                $this->command->info("  created  [{$name}] for partner #{$partnerId}");
            }
        }

        $this->command->info('GLP offerings seeded.');
    }
}
