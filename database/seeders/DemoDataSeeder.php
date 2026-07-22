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
                'licensed_states' => ['CA', 'NY', 'TX', 'FL', 'WA'],
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
                    ['name' => 'Semaglutide Monthly',  'sku' => 'SEMA_M2M',  'price' => 249, 'quantity' => 30,  'unit' => 'mg', 'days_supply' => 30,  'refills' => 1],
                    ['name' => 'Semaglutide 3-Month',  'sku' => 'SEMA_3M',   'price' => 596, 'quantity' => 90,  'unit' => 'mg', 'days_supply' => 90,  'refills' => 1],
                    ['name' => 'Semaglutide 6-Month',  'sku' => 'SEMA_6M',   'price' => 1050, 'quantity' => 180, 'unit' => 'mg', 'days_supply' => 180, 'refills' => 1],
                    ['name' => 'Semaglutide 12-Month', 'sku' => 'SEMA_12M',  'price' => 1800, 'quantity' => 360, 'unit' => 'mg', 'days_supply' => 365, 'refills' => 1],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Tirzepatide Monthly',  'sku' => 'TIRZ_M2M',  'price' => 359, 'quantity' => 15,  'unit' => 'mg', 'days_supply' => 30,  'refills' => 1],
                    ['name' => 'Tirzepatide 3-Month',  'sku' => 'TIRZ_3M',   'price' => 896, 'quantity' => 45,  'unit' => 'mg', 'days_supply' => 90,  'refills' => 1],
                    ['name' => 'Tirzepatide 6-Month',  'sku' => 'TIRZ_6M',   'price' => 1650, 'quantity' => 90,  'unit' => 'mg', 'days_supply' => 180, 'refills' => 1],
                    ['name' => 'Tirzepatide 12-Month', 'sku' => 'TIRZ_12M',  'price' => 2880, 'quantity' => 180, 'unit' => 'mg', 'days_supply' => 365, 'refills' => 1],
                ],
            ],

            // ===== Upsell Products =====
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'LipoC 1-Month', 'sku' => 'LIPOC_1M', 'price' => 149, 'refills' => 0],
                    ['name' => 'LipoC 3-Month', 'sku' => 'LIPOC_3M', 'price' => 99,  'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'AntiAging',
                'variants' => [
                    ['name' => 'Tesamorelin 3-Month', 'sku' => 'TESA_3M', 'price' => 149, 'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'AntiAging',
                'variants' => [
                    ['name' => 'NAD+ (1000mg) 1-Month', 'sku' => 'NAD_1M', 'price' => 209, 'refills' => 0],
                    ['name' => 'NAD+ (1000mg) 3-Month', 'sku' => 'NAD_3M', 'price' => 149, 'refills' => 0],
                ],
            ],
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Zofran 30ct', 'sku' => 'ZOFRAN_30', 'price' => 49, 'refills' => 0],
                ],
            ],

            // ===== Cross-sell Products =====
            [
                'dea' => 'no',
                'product_category' => 'WeightLoss',
                'variants' => [
                    ['name' => 'Nutrition & Training App (Semaglutide M2M)',   'sku' => 'NTA_SEMA_M2M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 3M)',    'sku' => 'NTA_SEMA_3M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 6M)',    'sku' => 'NTA_SEMA_6M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Semaglutide 12M)',   'sku' => 'NTA_SEMA_12M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide M2M)',   'sku' => 'NTA_TIRZ_M2M',  'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 3M)',    'sku' => 'NTA_TIRZ_3M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 6M)',    'sku' => 'NTA_TIRZ_6M',   'price' => 0, 'refills' => 0],
                    ['name' => 'Nutrition & Training App (Tirzepatide 12M)',   'sku' => 'NTA_TIRZ_12M',  'price' => 0, 'refills' => 0],
                ],
            ],
        ];

        foreach ($items as $item) {
            foreach ($item['variants'] as $variant) {
                Offering::firstOrCreate(
                    ['name' => $variant['name'], 'partner_id' => $partner->id],
                    [
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
