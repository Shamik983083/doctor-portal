<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\OfferingCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DrAnaLisaCarrSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('production')) {
            $this->command->error('This seeder is for production only. Aborting.');
            return;
        }

        $email    = 'drcarr@lion.md';
        $password = Str::random(12) . rand(10, 99) . '!';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Ana Lisa Carr',
                'password' => Hash::make($password),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $this->command->warn("User {$email} already exists — skipping creation, password unchanged.");
            $password = '(existing account — password not changed)';
        }

        if (! $user->hasRole('clinician')) {
            $user->assignRole('clinician');
        }

        // DEA numbers are not stored — current schema has no DEA field.
        // They are documented here as comments for reference:
        //   CA: FC2859304 exp 2026-08-31 | FL: FC9930404 exp 2026-08-31
        //   IN: FC0514984 exp 2027-08-31 | KY: FC2174821 exp 2028-08-31
        //   NY: FC2926888 exp 2026-08-31 | OH: FC3208445 exp 2026-08-31
        //   PA: FC9975206 exp 2026-08-31 | TN: FC6107127 exp 2028-08-31
        //   TX: FC2824856 exp 2026-08-31 | WA: FC2867414 exp 2026-08-31
        $licensedStates = [
            ['state' => 'AK', 'license_number' => '193449',        'expiry_date' => '2026-12-31'],
            ['state' => 'AL', 'license_number' => '41936',         'expiry_date' => '2026-12-31'],
            ['state' => 'AZ', 'license_number' => '59937',         'expiry_date' => '2027-08-07'],
            ['state' => 'AR', 'license_number' => 'E-15798',       'expiry_date' => '2026-08-31'],
            ['state' => 'CA', 'license_number' => 'C 182571',      'expiry_date' => '2026-09-30'],
            ['state' => 'CO', 'license_number' => 'CDR.0000433',   'expiry_date' => '2027-04-30'],
            ['state' => 'CT', 'license_number' => '66570',         'expiry_date' => '2026-08-31'],
            ['state' => 'DC', 'license_number' => 'MD600003557',   'expiry_date' => '2027-08-31'],
            ['state' => 'DE', 'license_number' => 'C1-0025-160',   'expiry_date' => '2027-03-31'],
            ['state' => 'FL', 'license_number' => 'ME112147',      'expiry_date' => '2028-01-31'],
            ['state' => 'GA', 'license_number' => '81534',         'expiry_date' => '2026-08-31'],
            ['state' => 'HI', 'license_number' => '23010',         'expiry_date' => '2028-01-31'],
            ['state' => 'IA', 'license_number' => 'MD-50184',      'expiry_date' => '2027-08-01'],
            ['state' => 'ID', 'license_number' => 'MC-0834',       'expiry_date' => '2028-08-07'],
            ['state' => 'IL', 'license_number' => '36151176',      'expiry_date' => '2026-08-31'],
            ['state' => 'IN', 'license_number' => '01082897A',     'expiry_date' => '2027-10-31'],
            ['state' => 'KS', 'license_number' => '04-42403',      'expiry_date' => '2027-07-31'],
            ['state' => 'KY', 'license_number' => '51812',         'expiry_date' => '2027-02-28'],
            ['state' => 'LA', 'license_number' => '332508',        'expiry_date' => '2026-08-31'],
            ['state' => 'MA', 'license_number' => '294545',        'expiry_date' => '2027-08-07'],
            ['state' => 'MD', 'license_number' => 'D94970',        'expiry_date' => '2026-09-30'],
            ['state' => 'ME', 'license_number' => 'MD23236',       'expiry_date' => '2027-08-31'],
            ['state' => 'MI', 'license_number' => '5315212669',    'expiry_date' => '2028-12-09'],
            ['state' => 'MN', 'license_number' => '65971',         'expiry_date' => '2026-08-31'],
            ['state' => 'MO', 'license_number' => '2019035173',    'expiry_date' => '2027-01-31'],
            ['state' => 'MS', 'license_number' => '28722',         'expiry_date' => '2027-06-30'],
            ['state' => 'MT', 'license_number' => '98574',         'expiry_date' => '2027-03-31'],
            ['state' => 'NE', 'license_number' => '31770',         'expiry_date' => '2026-10-01'],
            ['state' => 'NV', 'license_number' => '20889',         'expiry_date' => '2027-06-30'],
            ['state' => 'NH', 'license_number' => '21389',         'expiry_date' => '2027-06-30'],
            ['state' => 'NJ', 'license_number' => '25MA10452800',  'expiry_date' => '2027-06-30'],
            ['state' => 'NM', 'license_number' => 'MD2023-0406',   'expiry_date' => '2029-07-01'],
            ['state' => 'NY', 'license_number' => '295893',        'expiry_date' => '2027-07-31'],
            ['state' => 'NC', 'license_number' => '2018-02096',    'expiry_date' => '2026-08-07'],
            ['state' => 'ND', 'license_number' => '16122',         'expiry_date' => '2027-08-07'],
            ['state' => 'OH', 'license_number' => '35.134417',     'expiry_date' => '2027-04-01'],
            ['state' => 'OK', 'license_number' => '37734',         'expiry_date' => '2027-02-01'],
            ['state' => 'OR', 'license_number' => 'MD213006',      'expiry_date' => '2027-12-31'],
            ['state' => 'PA', 'license_number' => 'MD469036',      'expiry_date' => '2026-12-31'],
            ['state' => 'RI', 'license_number' => '18750',         'expiry_date' => '2028-06-30'],
            ['state' => 'SC', 'license_number' => '61275',         'expiry_date' => '2027-06-30'],
            ['state' => 'SD', 'license_number' => '13735',         'expiry_date' => '2027-03-01'],
            ['state' => 'TN', 'license_number' => '53623',         'expiry_date' => '2027-08-31'],
            ['state' => 'TX', 'license_number' => 'T8707',         'expiry_date' => '2027-08-30'],
            ['state' => 'UT', 'license_number' => '12925500-1205', 'expiry_date' => '2028-01-01'],
            ['state' => 'VA', 'license_number' => '101265588',     'expiry_date' => '2026-08-31'],
            ['state' => 'VT', 'license_number' => '42.0016269',    'expiry_date' => '2026-11-30'],
            ['state' => 'WA', 'license_number' => '61193803',      'expiry_date' => '2026-08-07'],
            ['state' => 'WV', 'license_number' => '30310',         'expiry_date' => '2028-06-30'],
        ];

        $clinician = Clinician::firstOrCreate(
            ['user_id' => $user->id],
            [
                'phone'                => '15616859743',
                'specialty'            => 'General Medicine',
                'credentials'          => 'MD',
                'licensed_states'      => $licensedStates,
                'status'               => 'active',
                'is_available'         => true,
                'is_global'            => true,
                'accepting_new_cases'  => true,
                'max_daily_cases'      => 20,
            ]
        );

        if (! $clinician->wasRecentlyCreated) {
            $this->command->warn('Clinician record already exists — licensed_states NOT overwritten. Edit via admin UI if needed.');
        }

        // Sync all active offering categories so she can receive routed cases.
        $allCategoryIds = OfferingCategory::pluck('id')->toArray();
        $clinician->acceptedCategories()->sync($allCategoryIds);

        $this->command->newLine();
        $this->command->info('Dr. Ana Lisa Carr — created successfully.');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Email',       $email],
                ['Password',    $password],
                ['States',      count($licensedStates) . ' licensed states loaded'],
                ['Categories',  count($allCategoryIds) . ' offering categories synced'],
            ]
        );
        $this->command->newLine();
        $this->command->warn('Share the temporary password securely and ask the clinician to change it on first login.');
        $this->command->newLine();
    }
}
