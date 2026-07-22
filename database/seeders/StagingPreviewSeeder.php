<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * TEMPORARY STAGING PREVIEW DATA. NOT FOR PRODUCTION. (Devin msg 2234,
 * "We'll need some temp seed data".)
 *
 * WHY THIS IS A SEEDER AND NOT A MIGRATION, WHICH MATTERS.
 * The deploy workflow runs `artisan migrate --force` automatically but never
 * runs seeders. It would therefore have been tempting to put this data in a
 * migration so it lands without anyone logging in. That would be a mistake: the
 * SAME migration set runs on production when this eventually merges to main, so
 * a seeding migration means demo doctors and fake patients appearing in a live
 * clinical system. Migrations describe schema. Fixtures are run on purpose, by a
 * person, on a box they chose. So this needs one deliberate command:
 *
 *     php artisan db:seed --class=Database\\Seeders\\StagingPreviewSeeder
 *
 * WHAT IT EXISTS TO MAKE VISIBLE. Three things that are otherwise invisible or
 * look broken on a fresh staging deploy:
 *
 *  1. THE ADMIN CONSOLE IS EMPTY WITHOUT IT. `admin_clinician` ships with no
 *     backfill, and a Doctor Admin over no doctors sees nothing by design. On a
 *     fresh deploy that is every existing admin, and it reads as "the merge
 *     broke the admin panel". This links them up.
 *  2. THE TWO TIERS ARE INDISTINGUISHABLE WITHOUT IT. One Doctor Admin over one
 *     doctor, a second doctor they are NOT over, so the scoping can actually be
 *     seen rather than taken on trust.
 *  3. THE WAITING-CASE FIX IS UNPROVABLE WITHOUT IT. Unassigned cases are the
 *     whole point of the scopeVisibleTo change; there have to be some.
 *
 * IDEMPOTENT. Every write is firstOrCreate / updateOrCreate / syncWithoutDetaching,
 * so re-running changes nothing. Safe to run twice.
 *
 * PASSWORDS ARE WEAK ON PURPOSE and are listed in docs/STAGING-DEPLOY-RUNBOOK.md.
 * axismd.io staging is demo mode with no real patients. If that ever stops being
 * true, delete this seeder rather than hardening it.
 */
class StagingPreviewSeeder extends Seeder
{
    private const PASSWORD = 'staging-preview-2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('StagingPreviewSeeder refuses to run with APP_ENV=production.');
            return;
        }

        $partner    = $this->partner();
        $mine       = $this->doctor('dr.alvarez@staging.axismd.io', 'Dr. Rosa Alvarez', '1780000001', ['TN', 'CA', 'NY']);
        $notMine    = $this->doctor('dr.okafor@staging.axismd.io',  'Dr. Daniel Okafor', '1780000002', ['TN', 'TX', 'FL']);
        $doctorAdmin = $this->doctorAdmin();

        /*
         * The scoping demo. The Doctor Admin is over Alvarez and NOT over Okafor.
         * syncWithoutDetaching so a re-run does not wipe a grant someone added by
         * hand while testing.
         */
        $doctorAdmin->managedClinicians()->syncWithoutDetaching([$mine->id]);

        $this->cases($partner, $mine, $notMine);
        $this->routingPolicy($doctorAdmin);

        $this->command->info('Staging preview data seeded.');
        $this->command->info("  Doctor Admin : doctor.admin@staging.axismd.io / " . self::PASSWORD);
        $this->command->info('  Over         : Dr. Rosa Alvarez only (NOT Dr. Daniel Okafor)');
        $this->command->info('  Expect       : their own doctor\'s cases + ALL waiting cases, and nothing of Okafor\'s');
    }

    private function partner(): Partner
    {
        return Partner::firstOrCreate(
            ['email' => 'storefront@staging.axismd.io'],
            [
                'name'   => 'Staging Storefront',
                'slug'   => 'staging-storefront',
                'status' => 'active',
            ]
        );
    }

    /** A clinician plus the user behind them. */
    private function doctor(string $email, string $name, string $npi, array $states): Clinician
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD)]
        );
        $user->assignRole('clinician');

        /*
         * licensed_states is populated deliberately. The compliance branch seals
         * the licence gate so that blank licensure blocks a doctor entirely, and
         * a seeded doctor with no states would look like the seal is broken when
         * it is working exactly as intended.
         */
        return Clinician::firstOrCreate(
            ['user_id' => $user->id],
            [
                'npi'             => $npi,
                'license_number'  => 'MD-' . $states[0] . '-' . substr($npi, -5),
                'license_state'   => $states[0],
                'specialty'       => 'General Medicine',
                'credentials'     => 'MD',
                'status'          => 'active',
                'is_available'    => true,
                // SHAPE MATTERS. `Clinician::isLicensedInState()` does
                // `collect($states)->pluck('state')`, and the admin UI writes
                // `['state' => .., 'license_number' => .., 'expiry_date' => ..]`.
                // A flat `['CA','NY']` list plucks to nulls and matches NOTHING.
                // See the DemoDataSeeder fix in this same commit.
                'licensed_states' => array_map(fn ($s) => [
                    'state'          => $s,
                    'license_number' => 'MD-' . $s . '-' . substr($npi, -5),
                    'expiry_date'    => now()->addYear()->toDateString(),
                ], $states),
                'max_daily_cases' => 20,
            ]
        );
    }

    private function doctorAdmin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'doctor.admin@staging.axismd.io'],
            ['name' => 'Staging Doctor Admin', 'password' => Hash::make(self::PASSWORD)]
        );

        // `admin`, NOT `super_admin`. A super admin is unscoped and would show
        // nothing about whether the scoping works.
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Cases in the three shapes that matter for the scoping fix:
     * waiting (no doctor), assigned to the admin's doctor, assigned to a doctor
     * they are not over.
     */
    private function cases(Partner $partner, Clinician $mine, Clinician $notMine): void
    {
        $rows = [
            ['Ada',   'Whitfield', 'TN', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_RED],
            ['Marcus', 'Lindqvist', 'TN', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_YELLOW],
            ['Priya', 'Raman',     'CA', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_GREEN],
            ['Joel',  'Berhane',   'TN', PatientCase::STATUS_ASSIGNED, $mine->id,     PatientCase::TRIAGE_YELLOW],
            ['Nina',  'Kowalski',  'CA', PatientCase::STATUS_ASSIGNED, $mine->id,     PatientCase::TRIAGE_GREEN],
            ['Terrence', 'Boyd',   'TX', PatientCase::STATUS_ASSIGNED, $notMine->id,  PatientCase::TRIAGE_RED],
            ['Sofia', 'Marchetti', 'FL', PatientCase::STATUS_ASSIGNED, $notMine->id,  PatientCase::TRIAGE_GREEN],
        ];

        foreach ($rows as $i => [$first, $last, $state, $status, $clinicianId, $triage]) {
            $externalId = 'staging-preview-' . ($i + 1);

            $patient = Patient::firstOrCreate(
                ['partner_id' => $partner->id, 'external_id' => $externalId],
                [
                    'first_name' => $first,
                    'last_name'  => $last,
                    // Unroutable domain on purpose: nothing here should ever be emailed.
                    'email'      => strtolower($first . '.' . $last) . '@staging.invalid',
                    'state'      => $state,
                    'gender'     => 'other',
                    'age'        => 34 + $i,
                    'status'     => 'active',
                ]
            );

            PatientCase::firstOrCreate(
                ['partner_id' => $partner->id, 'external_id' => $externalId],
                [
                    'patient_id'    => $patient->id,
                    'clinician_id'  => $clinicianId,
                    'status'        => $status,
                    'patient_state' => $state,
                    'triage'        => $triage,
                    'assigned_at'   => $clinicianId ? now() : null,
                ]
            );
        }
    }

    /**
     * An ACTIVE routing policy, because RoutingPolicy::active() legitimately
     * returns null and nothing is auto-assigned when it does. Without one the
     * routing screen is empty and reads as broken rather than unconfigured.
     *
     * requireRecordedLicensure is left FALSE here, matching the shipped default.
     * Flipping it is a real operational decision that belongs in the runbook
     * after `licensure:audit` passes, not something a fixture decides.
     */
    private function routingPolicy(User $createdBy): void
    {
        if (RoutingPolicy::active()) {
            return;
        }

        DB::transaction(function () use ($createdBy) {
            RoutingPolicy::updateOrCreate(
                ['version' => 1],
                [
                    'mode'         => 'PRIORITY',
                    'config'       => ['requireRecordedLicensure' => false],
                    'status'       => RoutingPolicy::STATUS_ACTIVE,
                    'activated_at' => now(),
                    'created_by'   => $createdBy->id,
                    'activated_by' => $createdBy->id,
                    'note'         => 'Staging preview default. Not a production policy.',
                ]
            );
        });
    }
}
