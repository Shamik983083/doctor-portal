<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\Disease;
use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Tag;
use App\Models\User;
use App\Models\Questionnaire;
use App\Models\Webhook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@doctorportal.com'],
            ['name' => 'System Admin', 'password' => Hash::make('password')]
        );
        $admin->assignRole('admin');

        // Demo clinician
        $clinicianUser = User::firstOrCreate(
            ['email' => 'dr.smith@doctorportal.com'],
            ['name' => 'Dr. Jane Smith', 'password' => Hash::make('password')]
        );
        $clinicianUser->assignRole('clinician');

        $clinician = Clinician::firstOrCreate(
            ['user_id' => $clinicianUser->id],
            [
                'npi'             => '1234567890',
                'license_number'  => 'MD-CA-12345',
                'license_state'   => 'CA',
                'specialty'       => 'General Medicine',
                'credentials'     => 'MD',
                'is_available'    => true,
                /*
                 * WRONG SHAPE UNTIL NOW, AND IT MATTERED.
                 *
                 * This was a flat `['CA','NY','TX','FL','WA']`. Every reader of
                 * this column does `collect($states)->pluck('state')`, which on
                 * a flat list yields [null, null, ...] and matches no state at
                 * all. So the demo clinician has always been licensed NOWHERE
                 * by `isLicensedInState()`, despite the column looking correct.
                 *
                 * It went unnoticed because CaseAutoAssigner falls back to an
                 * unlicensed clinician when nobody matches, so cases still got
                 * routed. Once the licence gate is sealed that fallback goes and
                 * this doctor is blocked from every case, which would read as
                 * the seal being broken rather than the fixture being wrong.
                 *
                 * The shape below is what the admin UI writes, in
                 * ClinicianController::store().
                 */
                'licensed_states' => array_map(fn ($s) => [
                    'state'          => $s,
                    'license_number' => 'MD-' . $s . '-12345',
                    'expiry_date'    => '2027-12-31',
                ], ['CA', 'NY', 'TX', 'FL', 'WA']),
                'max_daily_cases' => 20,
            ]
        );

        // Demo partner
        $partner = Partner::firstOrCreate(
            ['email' => 'partner@ameriLean.com'],
            [
                'name'   => 'AmeriLean',
                'slug'   => 'ameriLean',
                'status' => 'active',
            ]
        );

        // Static API credentials for demo partner
        if (! $partner->client_id) {
            $partner->update([
                'oauth_client_id' => '019f898d-7db6-7097-9429-eb3738e8001e',
                'client_id'       => 'jG1i928eYkzNFwWuwwnJoQEWZp3tL1IiuyqUAQ8l',
                'client_secret'   => 'YNdxm2ustsa8UqRndqsVk7yCOivI8Ov1',
            ]);
        }

        // Pharmacies
        $pharmacy = Pharmacy::firstOrCreate(
            ['name' => 'Boothwyn Pharmacy'],
            [
                'type'      => 'boothwyn',
                'state'     => 'PA',
                'phone'     => '8005551234',
                'is_active' => true,
            ]
        );

        // Offering Categories
        $weightLossCategory = OfferingCategory::firstOrCreate(
            ['name' => 'Weight Loss'],
            ['description' => 'Weight loss medications and supplements', 'is_active' => true]
        );
        $antiAgingCategory = OfferingCategory::firstOrCreate(
            ['name' => 'Anti Aging'],
            ['description' => 'Anti-aging treatments and therapies', 'is_active' => true]
        );

        $categoryMap = [
            'WeightLoss' => $weightLossCategory->id,
            'AntiAging'  => $antiAgingCategory->id,
        ];

        // Offerings (mapped from tenant portal products) 
        $items = [
            // ===== Regular Products =====
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Semaglutide Monthly',  'uuid' => 'e7ea33b9-46ea-46b6-8544-e91bba16f550', 'sku' => 'SEMA_M2M',  'price' => 249, 'quantity' => 30,  'unit' => 'mg', 'days_supply' => 30,  'refills' => 1],
                    ['name' => 'Semaglutide 3-Month',  'uuid' => '24167a5a-f10b-4913-ae03-cba5792f2b2c', 'sku' => 'SEMA_3M',   'price' => 596, 'quantity' => 90,  'unit' => 'mg', 'days_supply' => 90,  'refills' => 1],
                    ['name' => 'Semaglutide 6-Month',  'uuid' => '0d2716a9-e214-4dee-8e74-f06cefd95c0d', 'sku' => 'SEMA_6M',   'price' => 1050, 'quantity' => 180, 'unit' => 'mg', 'days_supply' => 180, 'refills' => 1],
                    ['name' => 'Semaglutide 12-Month', 'uuid' => 'd579597c-aadc-4b12-a9d8-7b91ceac024b', 'sku' => 'SEMA_12M',  'price' => 1800, 'quantity' => 360, 'unit' => 'mg', 'days_supply' => 365, 'refills' => 1],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Tirzepatide Monthly',  'uuid' => 'fbcd7604-fd4e-47ae-846a-bc2907fd17a2', 'sku' => 'TIRZ_M2M',  'price' => 359, 'quantity' => 15,  'unit' => 'mg', 'days_supply' => 30,  'refills' => 1],
                    ['name' => 'Tirzepatide 3-Month',  'uuid' => 'ba673659-48ba-4322-8f4c-c75018bfdef0', 'sku' => 'TIRZ_3M',   'price' => 896, 'quantity' => 45,  'unit' => 'mg', 'days_supply' => 90,  'refills' => 1],
                    ['name' => 'Tirzepatide 6-Month',  'uuid' => '6524da26-de62-42e9-9ba9-109e517e39ad', 'sku' => 'TIRZ_6M',   'price' => 1650, 'quantity' => 90,  'unit' => 'mg', 'days_supply' => 180, 'refills' => 1],
                    ['name' => 'Tirzepatide 12-Month', 'uuid' => 'de698464-9947-4ea8-b9d6-e4352136f2e4', 'sku' => 'TIRZ_12M',  'price' => 2880, 'quantity' => 180, 'unit' => 'mg', 'days_supply' => 365, 'refills' => 1],
                ],
            ],

            // ===== Upsell Products =====
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'LipoC 1-Month', 'uuid' => '4a1da53c-ab4a-4387-abb6-2b1f0503a8e0', 'sku' => 'LIPOC_1M', 'price' => 149, 'refills' => 0],
                    ['name' => 'LipoC 3-Month', 'uuid' => '3bdc1d45-623a-44ab-bf60-037742388b92', 'sku' => 'LIPOC_3M', 'price' => 99,  'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'AntiAging',
                'variants' => [
                    ['name' => 'Tesamorelin 3-Month', 'uuid' => '8a58ca3b-38ab-478a-bec5-e93cc28509db', 'sku' => 'TESA_3M', 'price' => 149, 'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'AntiAging',
                'variants' => [
                    ['name' => 'NAD+ (1000mg) 1-Month', 'uuid' => 'b09dd040-82f8-4a38-88c9-c7010ff43507', 'sku' => 'NAD_1M', 'price' => 209, 'refills' => 0],
                    ['name' => 'NAD+ (1000mg) 3-Month', 'uuid' => '97b2c85e-fbb3-43f8-b452-4535a122c787', 'sku' => 'NAD_3M', 'price' => 149, 'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Zofran 30ct', 'uuid' => '09c6c7c8-7062-4eaf-826b-2ae519da34dd', 'sku' => 'ZOFRAN_30', 'price' => 49, 'refills' => 0],
                ],
            ],

            // ===== Cross-sell Products =====
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Nutrition & Training App (Semaglutide M2M)',   'uuid' => '613a2b1b-2b68-43e1-97b3-f9238783d03f', 'sku' => 'NTA_SEMA_M2M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 3M)',    'uuid' => 'c9df1a9b-663a-4e68-ba2a-a55afa53f333', 'sku' => 'NTA_SEMA_3M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 6M)',    'uuid' => '3e6f4a4f-dc92-41b6-b0cd-1d51831db859', 'sku' => 'NTA_SEMA_6M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 12M)',   'uuid' => 'eb325970-6a28-44ca-84f2-6c480cd6bd60', 'sku' => 'NTA_SEMA_12M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide M2M)',   'uuid' => '369deccf-21c0-44c6-aa8b-d1ddafe270e9', 'sku' => 'NTA_TIRZ_M2M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 3M)',    'uuid' => 'b04fbaa1-4335-449d-a225-6f1b2fcb27a5', 'sku' => 'NTA_TIRZ_3M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 6M)',    'uuid' => 'a36205d1-703d-43fc-8f0b-a4dcdaf5582a', 'sku' => 'NTA_TIRZ_6M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 12M)',   'uuid' => '2816f4af-1bcd-4ef7-872b-1fd373affd85', 'sku' => 'NTA_TIRZ_12M',  'price' => 0, 'refills' => 0],
                ],
            ],
        ];

        foreach ($items as $item) {
            foreach ($item['variants'] as $variant) {
                Offering::firstOrCreate(
                    ['name' => $variant['name'], 'partner_id' => $partner->id],
                    [
                        'uuid'                    => $variant['uuid'],
                        'partner_id'              => $partner->id,
                        'category_id'             => $categoryMap[$item['product_category']],
                        'type'                    => 'compound',
                        'sku'                     => $variant['sku'],
                        'price'                   => $variant['price'],
                        'is_active'               => true,
                        'is_controlled_substance' => $item['dea'] === 'yes',
                        'refills'                 => $variant['refills'],
                        'quantity'                => $variant['quantity'] ?? null,
                        'dispense_unit'           => $variant['unit'] ?? null,
                        'days_supply'             => $variant['days_supply'] ?? null,
                        'approval_status'         => 'approved',
                    ]
                );
            }
        }

        // Link weight loss offerings to questionnaires
        $mwlQuestionnaire = Questionnaire::where('name', 'like', '%Weight Loss%')->first();

        if ($mwlQuestionnaire) {
            $weightLossOfferings = Offering::where('partner_id', $partner->id)
                ->where('category_id', $weightLossCategory->id)
                ->get();

            foreach ($weightLossOfferings as $offering) {
                DB::table('offering_questionnaire')->insertOrIgnore([
                    'offering_id'      => $offering->id,
                    'questionnaire_id' => $mwlQuestionnaire->id,
                    'is_required'      => true,
                    'sort_order'       => 0,
                ]);
            }
        }

        // Webhook for demo partner
        Webhook::firstOrCreate(
            ['partner_id' => $partner->id, 'url' => 'http://localhost:8000/api/v1/webhooks/doctor-network'],
            ['status' => 'active', 'event_type' => null]
        );

        // Demo patient
        Patient::firstOrCreate(
            ['email' => 'john.doe@example.com', 'partner_id' => $partner->id],
            [
                'first_name'    => 'John',
                'last_name'     => 'Doe',
                'phone'         => '5551234567',
                'date_of_birth' => '1985-06-15',
                'gender'        => 'male',
                'address'       => '123 Main St',
                'city'          => 'Los Angeles',
                'state'         => 'CA',
                'zip'           => '90001',
                'status'        => 'active',
            ]
        );

        // Diseases
        $diseases = [
            ['icd_code' => 'E11', 'name' => 'Type 2 Diabetes Mellitus'],
            ['icd_code' => 'E66', 'name' => 'Obesity'],
            ['icd_code' => 'I10', 'name' => 'Essential Hypertension'],
            ['icd_code' => 'E78', 'name' => 'Hyperlipidemia'],
            ['icd_code' => 'Z68', 'name' => 'Body Mass Index (BMI)'],
        ];
        foreach ($diseases as $d) {
            Disease::firstOrCreate(['icd_code' => $d['icd_code']], $d);
        }

        // Tags
        $tags = [
            ['name' => 'VIP', 'type' => 'patient', 'color' => '#ffd700'],
            ['name' => 'Urgent', 'type' => 'case', 'color' => '#dc3545'],
            ['name' => 'Weight Loss', 'type' => 'case', 'color' => '#198754'],
            ['name' => 'Diabetes', 'type' => 'case', 'color' => '#0d6efd'],
        ];
        foreach ($tags as $t) {
            Tag::firstOrCreate(['slug' => Str::slug($t['name'])], $t);
        }
    }
}
