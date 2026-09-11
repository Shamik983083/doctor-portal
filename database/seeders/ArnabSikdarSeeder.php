<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\OfferingCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ArnabSikdarSeeder extends Seeder
{
    public function run(): void
    {
        $email    = 'arnab.sikdar@codeclouds.com';
        $password = 'P@ssw0rd';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Arnab Sikdar',
                'password' => Hash::make($password),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $this->command->warn("User {$email} already exists — updating password.");
            $user->update(['password' => Hash::make($password)]);
        }

        if (! $user->hasRole('clinician')) {
            $user->assignRole('clinician');
        }

        $allStates = [
            ['state' => 'AK', 'license_number' => 'TEST-AK-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'AL', 'license_number' => 'TEST-AL-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'AR', 'license_number' => 'TEST-AR-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'AZ', 'license_number' => 'TEST-AZ-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'CA', 'license_number' => 'TEST-CA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'CO', 'license_number' => 'TEST-CO-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'CT', 'license_number' => 'TEST-CT-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'DC', 'license_number' => 'TEST-DC-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'DE', 'license_number' => 'TEST-DE-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'FL', 'license_number' => 'TEST-FL-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'GA', 'license_number' => 'TEST-GA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'HI', 'license_number' => 'TEST-HI-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'IA', 'license_number' => 'TEST-IA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'ID', 'license_number' => 'TEST-ID-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'IL', 'license_number' => 'TEST-IL-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'IN', 'license_number' => 'TEST-IN-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'KS', 'license_number' => 'TEST-KS-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'KY', 'license_number' => 'TEST-KY-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'LA', 'license_number' => 'TEST-LA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MA', 'license_number' => 'TEST-MA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MD', 'license_number' => 'TEST-MD-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'ME', 'license_number' => 'TEST-ME-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MI', 'license_number' => 'TEST-MI-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MN', 'license_number' => 'TEST-MN-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MO', 'license_number' => 'TEST-MO-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MS', 'license_number' => 'TEST-MS-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'MT', 'license_number' => 'TEST-MT-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NC', 'license_number' => 'TEST-NC-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'ND', 'license_number' => 'TEST-ND-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NE', 'license_number' => 'TEST-NE-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NH', 'license_number' => 'TEST-NH-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NJ', 'license_number' => 'TEST-NJ-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NM', 'license_number' => 'TEST-NM-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NV', 'license_number' => 'TEST-NV-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'NY', 'license_number' => 'TEST-NY-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'OH', 'license_number' => 'TEST-OH-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'OK', 'license_number' => 'TEST-OK-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'OR', 'license_number' => 'TEST-OR-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'PA', 'license_number' => 'TEST-PA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'RI', 'license_number' => 'TEST-RI-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'SC', 'license_number' => 'TEST-SC-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'SD', 'license_number' => 'TEST-SD-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'TN', 'license_number' => 'TEST-TN-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'TX', 'license_number' => 'TEST-TX-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'UT', 'license_number' => 'TEST-UT-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'VA', 'license_number' => 'TEST-VA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'VT', 'license_number' => 'TEST-VT-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'WA', 'license_number' => 'TEST-WA-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'WI', 'license_number' => 'TEST-WI-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'WV', 'license_number' => 'TEST-WV-001', 'expiry_date' => '2030-12-31'],
            ['state' => 'WY', 'license_number' => 'TEST-WY-001', 'expiry_date' => '2030-12-31'],
        ];

        $clinician = Clinician::firstOrCreate(
            ['user_id' => $user->id],
            [
                'phone'               => '9999999999',
                'specialty'           => 'General Medicine',
                'credentials'         => 'MD',
                'licensed_states'     => $allStates,
                'status'              => 'active',
                'is_available'        => true,
                'is_global'           => true,
                'accepting_new_cases' => true,
                'max_daily_cases'     => 20,
            ]
        );

        if (! $clinician->wasRecentlyCreated) {
            $clinician->update(['licensed_states' => $allStates, 'status' => 'active', 'is_available' => true]);
            $this->command->warn('Clinician record already existed — licensed_states overwritten.');
        }

        $allCategoryIds = OfferingCategory::pluck('id')->toArray();
        $clinician->acceptedCategories()->sync($allCategoryIds);

        $this->command->newLine();
        $this->command->info('Arnab Sikdar — clinician created/updated successfully.');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Email',      $email],
                ['Password',   $password],
                ['States',     count($allStates) . ' states loaded'],
                ['Categories', count($allCategoryIds) . ' offering categories synced'],
            ]
        );
        $this->command->newLine();
    }
}
