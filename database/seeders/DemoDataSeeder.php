<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\Disease;
use App\Models\Offering;
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
            ['email' => 'partner@demostore.com'],
            [
                'name'   => 'Demo Health Store',
                'slug'   => 'demo-health-store',
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

        // Offerings
        $offerings = [
            ['name' => 'Semaglutide 0.5mg', 'type' => 'compound', 'price' => 299.00, 'available_states' => ['CA','NY','TX','FL']],
            ['name' => 'Tirzepatide 2.5mg', 'type' => 'compound', 'price' => 349.00, 'available_states' => ['CA','NY','TX']],
            ['name' => 'Metformin 500mg',   'type' => 'medication', 'price' => 49.00,  'available_states' => null],
            ['name' => 'Blood Glucose Kit', 'type' => 'supply',    'price' => 29.00,  'available_states' => null],
        ];

        foreach ($offerings as $o) {
            Offering::firstOrCreate(
                ['name' => $o['name'], 'partner_id' => $partner->id],
                array_merge($o, ['partner_id' => $partner->id, 'is_active' => true])
            );
        }

        // Link offerings to questionnaires
        $semaglutide = Offering::where('name', 'Semaglutide 0.5mg')->where('partner_id', $partner->id)->first();
        $tirzepatide = Offering::where('name', 'Tirzepatide 2.5mg')->where('partner_id', $partner->id)->first();
        $mwlQuestionnaire = Questionnaire::where('name', 'like', '%Weight Loss%')->first();

        if ($mwlQuestionnaire) {
            foreach (array_filter([$semaglutide, $tirzepatide]) as $offering) {
                DB::table('offering_questionnaire')->insertOrIgnore([
                    'offering_id' => $offering->id,
                    'questionnaire_id' => $mwlQuestionnaire->id,
                    'is_required' => true,
                    'sort_order' => 0,
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
