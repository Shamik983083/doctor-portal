<?php

namespace Database\Seeders;

use App\Models\Clinician;
use App\Models\OfferingCategory;
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

        $partner     = $this->partner();
        $superAdmin  = $this->superAdmin();
        $mine        = $this->doctor('dr.alvarez@staging.axismd.io', 'Dr. Rosa Alvarez', '1780000001', ['TN', 'CA', 'NY']);
        $notMine     = $this->doctor('dr.okafor@staging.axismd.io',  'Dr. Daniel Okafor', '1780000002', ['TN', 'TX', 'FL']);
        $doctorAdmin = $this->doctorAdmin();
        $this->partnerUser($partner);

        /*
         * The scoping demo. The Doctor Admin is over Alvarez and NOT over Okafor.
         * syncWithoutDetaching so a re-run does not wipe a grant someone added by
         * hand while testing.
         */
        $doctorAdmin->managedClinicians()->syncWithoutDetaching([$mine->id]);

        /*
         * THE v2 ELIGIBILITY GATE NEEDS THE DOCTORS OPTED IN, AND THIS IS NOT
         * OPTIONAL FIXTURE POLISH. Routing v2 made accepted categories a hard
         * fail-closed block: a doctor with none accepts NOTHING.
         *
         * The migration backfills every clinician that existed WHEN IT RAN, which
         * covers the upgrade. It cannot cover a category created afterwards, and
         * on staging that is exactly what happened: the GLP offerings seeder
         * landed after the routing v2 migration, so both demo doctors were left
         * accepting nothing and no case could route to anyone. Verified on
         * staging 2026-07-23: "Categories you accept: None ticked".
         *
         * Doing it here rather than in the migration is deliberate. A fixture
         * that re-runs on every deploy is the only thing that keeps up with a
         * taxonomy people keep adding to.
         */
        $this->acceptEveryCategory($mine, $notMine);

        $this->cases($partner, $mine, $notMine);
        $this->messages($partner, $mine);
        $this->routingPolicy($superAdmin);

        $this->command->info('Staging preview data seeded. Password for all: ' . self::PASSWORD);
        $this->command->info('  Super Admin  : super.admin@staging.axismd.io');
        $this->command->info('  Doctor Admin : doctor.admin@staging.axismd.io  (over Dr. Alvarez only)');
        $this->command->info('  Clinician    : dr.alvarez@staging.axismd.io / dr.okafor@staging.axismd.io');
        $this->command->info('  Partner      : partner@staging.axismd.io');
        $this->command->info('  Expect (Doctor Admin): their doctor\'s cases + ALL waiting cases, nothing of Okafor\'s');
    }

    /**
     * THE ACCOUNT NOBODY HAD (Devin msg 2238, then 2241 "Fix 1 and 2").
     *
     * RolesAndPermissionsSeeder creates the `super_admin` ROLE and grants it
     * every permission, but no seeder has ever assigned that role to a user. So
     * the role existed and nobody held it.
     *
     * That was survivable while `admin` and `super_admin` were effectively the
     * same thing. It stopped being survivable when Partners, SLA Settings,
     * Triage Rule Set, Case Routing, Admin Users, the API guides and every
     * offering WRITE moved behind `role:super_admin`: those screens became
     * unreachable by anyone at all, and /ma-portal/super-admin 403'd for
     * everybody.
     */
    private function superAdmin(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'super.admin@staging.axismd.io'],
            ['name' => 'Staging Super Admin', 'password' => Hash::make(self::PASSWORD)]
        );

        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * A partner (storefront operator) login, which no seeder created either.
     *
     * DemoDataSeeder made the Partner ORGANISATION but never a user attached to
     * one, and the only other way to create one is through
     * /admin/partners/{id}/users/create, which is itself super-admin-only. So
     * this gap was locked behind the previous one.
     *
     * Without this account the most important line of the smoke test cannot be
     * run at all: confirming that a PARTNER login is refused by /ma-portal/*.
     * That route was gated on `auth` alone and exposed patient demographics,
     * intake answers, clinical notes and the full user roster to any logged-in
     * user. Being able to prove it is closed matters more than the convenience.
     *
     * `partner_id` is what PartnerPortalAccess checks, so it is set here
     * directly, matching PartnerController::storeUser().
     */
    private function partnerUser(Partner $partner): User
    {
        $user = User::firstOrCreate(
            ['email' => 'partner@staging.axismd.io'],
            [
                'name'       => 'Staging Storefront Operator',
                'password'   => Hash::make(self::PASSWORD),
                'partner_id' => $partner->id,
            ]
        );

        // firstOrCreate skips the attribute block when the row already exists,
        // so a re-run against a user made by hand still gets linked.
        if ($user->partner_id !== $partner->id) {
            $user->update(['partner_id' => $partner->id]);
        }

        $user->assignRole('partner');

        return $user;
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

    /**
     * Make sure the demo categories exist, and tick every doctor into all of them.
     *
     * THE CATEGORY LIST. Devin msg 2313: "Currently we have GLP, NAD, Anti-Aging,
     * Peptides, ED. We want to be able to add and remove as we go." Staging had
     * only GLP, so the rest are created here as fixtures. firstOrCreate on name,
     * so a category an admin already added by hand is left exactly as it is,
     * including one they deliberately deactivated.
     *
     * THEN EVERY ACTIVE CATEGORY IS ATTACHED TO BOTH DEMO DOCTORS, including any
     * an admin added themselves. On a demo box the useful default is a doctor who
     * can take anything; narrowing one is the thing you then demonstrate, by
     * unticking a box and watching a case land in the exceptions queue with
     * CATEGORY_NOT_ACCEPTED.
     *
     * syncWithoutDetaching, so unticking a category by hand to try that out is not
     * silently undone by the next deploy.
     */
    private function acceptEveryCategory(Clinician ...$clinicians): void
    {
        foreach (['GLP', 'NAD', 'Anti-Aging', 'Peptides', 'ED'] as $name) {
            OfferingCategory::firstOrCreate(
                ['name' => $name],
                ['description' => 'Staging preview category.', 'is_active' => true],
            );
        }

        $categoryIds = OfferingCategory::where('is_active', true)->pluck('id')->all();

        if ($categoryIds === []) {
            $this->command->warn('No active product categories: demo doctors will accept nothing and no case will route.');

            return;
        }

        foreach ($clinicians as $clinician) {
            $clinician->acceptedCategories()->syncWithoutDetaching($categoryIds);
        }

        $this->command->info('  Categories   : ' . count($categoryIds) . ' active, all accepted by both demo doctors');
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
        // Each row also carries a clinical_intake block in the design preview's
        // exact shape (Devin msg 2258), so the queue's medication columns and the
        // quick-review drawer render with real-looking data on staging instead of
        // a wall of dashes. Fictitious patients; safe on demo staging.
        // Last element is is_refill: two of Dr. Alvarez's cases are check-ins from
        // returning patients so the Refills screen is populated (Devin msg 2290).
        $rows = [
            ['Ada',   'Whitfield', 'TN', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_RED,
             ['Semaglutide', 'L1 · 2.5 mg', '3M', 'Titration', 'Zofran', '-', '-', 'N', 'Y', 'Y', 'Reports hives after penicillin; documented at intake.'], false],
            ['Marcus', 'Lindqvist', 'TN', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_YELLOW,
             ['Tirzepatide', 'L3 · 7.5 mg', '4M', 'Hold', 'NAD+', '-', '-', 'Y', 'N', 'N', null], false],
            ['Priya', 'Raman',     'CA', PatientCase::STATUS_WAITING,  null,          PatientCase::TRIAGE_GREEN,
             ['Semaglutide', 'L1 · 2.5 mg', '1M', 'Titration', '-', '-', '-', 'N', 'N', 'N', null], false],
            ['Joel',  'Berhane',   'TN', PatientCase::STATUS_ASSIGNED, $mine->id,     PatientCase::TRIAGE_YELLOW,
             ['Tirzepatide', 'L2 · 5 mg', '3M', 'Titration', 'Zofran', '-', '-', 'N', 'Y', 'N', null], true],
            ['Nina',  'Kowalski',  'CA', PatientCase::STATUS_ASSIGNED, $mine->id,     PatientCase::TRIAGE_GREEN,
             ['Semaglutide', 'L2 · 5 mg', '3M', 'Titration', '-', '-', '-', 'N', 'N', 'N', null], true],
            ['Terrence', 'Boyd',   'TX', PatientCase::STATUS_ASSIGNED, $notMine->id,  PatientCase::TRIAGE_RED,
             ['Tirzepatide', 'L4 · 10 mg', '6M', 'Hold', 'NAD+', 'Zofran', '-', 'Y', 'Y', 'Y', 'Sulfa drugs, rash reported at intake.'], false],
            ['Sofia', 'Marchetti', 'FL', PatientCase::STATUS_ASSIGNED, $notMine->id,  PatientCase::TRIAGE_GREEN,
             ['Semaglutide', 'L1 · 2.5 mg', '3M', 'Titration', '-', '-', '-', 'N', 'N', 'N', null], false],
        ];

        foreach ($rows as $i => [$first, $last, $state, $status, $clinicianId, $triage, $ci, $isRefill]) {
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

            [$product, $dose, $term, $plan, $med2, $med3, $med4, $onGlp, $zofran, $allergy, $allergyDetail] = $ci;

            $clinicalIntake = [
                'product'         => $product,
                'dose'            => $dose,
                'term'            => $term,
                'plan'            => $plan,
                'med2'            => $med2,
                'med3'            => $med3,
                'med4'            => $med4,
                'onGlp'           => $onGlp,
                'zofran'          => $zofran,
                'allergy'         => $allergy,
                'allergyDetail'   => $allergyDetail,
                'video'           => $triage === PatientCase::TRIAGE_GREEN ? 'Clear' : 'Required',
                'protocolVersion' => 'GLP-1 protocol v8',
                'findings'        => [
                    [$triage === PatientCase::TRIAGE_GREEN ? 'green' : ($triage === PatientCase::TRIAGE_RED ? 'red' : 'yellow'),
                     $triage === PatientCase::TRIAGE_GREEN
                        ? 'Requested dose matches the current protocol titration step.'
                        : 'Requested regimen needs clinician review.'],
                ],
                'summary'         => [
                    ['Requested regimen: ' . $term . ' term, dose ' . $dose . ', plan ' . $plan . '.', ['requestedTerm', 'requestedDoseLevel', 'dosePlan']],
                    ['Currently on GLP-1 therapy: ' . ($onGlp === 'Y' ? 'yes' : 'no') . '.', ['currentlyOnGlp']],
                    ['Concerning allergies flagged: ' . ($allergy === 'Y' ? 'yes.' : 'no.'), ['concerningAllergies']],
                ],
                'sourceAnswers'   => [
                    'requestedTerm'       => $term,
                    'requestedDoseLevel'  => $dose,
                    'dosePlan'            => $plan,
                    'currentlyOnGlp'      => $onGlp === 'Y' ? 'Yes' : 'No',
                    'concerningAllergies' => $allergy === 'Y' ? 'Yes' : 'No',
                    'allergyDetail'       => $allergyDetail,
                ],
            ];

            // updateOrCreate (not firstOrCreate) so a re-run backfills the
            // clinical_intake onto cases seeded before this column existed.
            PatientCase::updateOrCreate(
                ['partner_id' => $partner->id, 'external_id' => $externalId],
                [
                    'patient_id'      => $patient->id,
                    'clinician_id'    => $clinicianId,
                    'status'          => $status,
                    'patient_state'   => $state,
                    'triage'          => $triage,
                    'is_refill'       => $isRefill,
                    'assigned_at'     => $clinicianId ? now() : null,
                    'clinical_intake' => $clinicalIntake,
                ]
            );
        }
    }

    /**
     * A short conversation on two of Dr. Alvarez's cases so the Messages screen
     * shows real threads instead of an empty state (Devin msg 2294). Idempotent:
     * skips a case that already has messages, so re-running does not pile them up.
     */
    private function messages(Partner $partner, Clinician $mine): void
    {
        $threads = [
            'staging-preview-4' => [   // Joel Berhane
                ['inbound',  'Hi doctor, I have a quick question about my dose this month.'],
                ['outbound', 'Of course, happy to help. What is your question?'],
                ['inbound',  'Should I stay at 5 mg or step up? I have had some nausea.'],
            ],
            'staging-preview-5' => [   // Nina Kowalski
                ['inbound',  'Thank you for approving my refill so quickly.'],
                ['outbound', 'You are welcome. Keep an eye on any side effects and message me anytime.'],
            ],
        ];

        foreach ($threads as $externalId => $msgs) {
            $case = PatientCase::where('partner_id', $partner->id)
                ->where('external_id', $externalId)->first();

            if (! $case || $case->messages()->exists()) {
                continue;
            }

            $when = now()->subHours(3);
            foreach ($msgs as [$direction, $body]) {
                // forceCreate so the custom created_at is kept (it is not in the
                // Message model's fillable, and distinct times keep the thread
                // in order and the time headers realistic).
                \App\Models\Message::forceCreate([
                    'uuid'        => (string) \Illuminate\Support\Str::uuid(),
                    'case_id'     => $case->id,
                    'patient_id'  => $case->patient_id,
                    'clinician_id'=> $direction === 'outbound' ? $mine->id : null,
                    'partner_id'  => $partner->id,
                    'direction'   => $direction,
                    'channel'     => 'portal',
                    'sender_type' => $direction === 'outbound' ? 'clinician' : 'patient',
                    'body'        => $body,
                    'is_read'     => $direction === 'outbound',
                    'created_at'  => $when,
                    'updated_at'  => $when,
                ]);
                $when = $when->copy()->addMinutes(12);
            }
        }
    }

    /**
     * An ACTIVE routing policy, because RoutingPolicy::active() legitimately
     * returns null and nothing is auto-assigned when it does. Without one the
     * routing screen is empty and reads as broken rather than unconfigured.
     *
     * requireRecordedLicensure is TRUE now. It used to be false here to match the
     * old shipped default; routing v2 flipped that default on Devin's instruction
     * (msg 2313, "empty should not show licensed everywhere it needs to reject"),
     * so leaving an explicit false would have made the staging fixture the one
     * place still running the old fail-open behaviour, which is the worst
     * possible place for it: the box people test the new behaviour on.
     *
     * Safe here because both demo doctors are seeded with real licensed_states.
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
                    'config'       => ['requireRecordedLicensure' => true],
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
