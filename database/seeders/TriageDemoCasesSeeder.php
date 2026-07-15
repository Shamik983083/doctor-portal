<?php

namespace Database\Seeders;

use App\Models\CaseOffering;
use App\Models\Offering;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Services\TriageClassifier;
use Illuminate\Database\Seeder;

/**
 * Slice B (demo data) — seeds a spread of waiting cases whose attributes
 * exercise every triage band, so the clinician queue visibly shows
 * Green / Yellow / Red. Fictional patients only. Idempotent by external_id.
 */
class TriageDemoCasesSeeder extends Seeder
{
    public function run(): void
    {
        $partner = Partner::first();
        if (! $partner) {
            $this->command?->warn('No partner found — run DemoDataSeeder first.');
            return;
        }

        $sema      = Offering::where('name', 'like', '%Semaglutide%')->first();
        $tirz      = Offering::where('name', 'like', '%Tirzepatide%')->first();
        $metformin = Offering::where('name', 'like', '%Metformin%')->first();

        $classifier = app(TriageClassifier::class);

        // [first, last, state, bmi, age, id_status, hold, offering, expected]
        $rows = [
            ['Maria',    'Alvarez',  'CA', 31.5, 42, 'verified', false, $metformin, 'green'],
            ['James',    'Whitfield','TX', 34.0, 38, 'verified', false, $metformin, 'green'],
            ['Priya',    'Nair',     'NY', 41.2, 45, 'verified', false, $sema,      'yellow'],  // high BMI + elevated Rx
            ['Robert',   'Chen',     'FL', 29.8, 67, 'pending',  false, $tirz,      'yellow'],  // geriatric + unverified + elevated
            ['Diane',    'Okafor',   'WA', 37.4, 51, 'verified', true,  $sema,      'yellow'],  // workflow hold
            ['Kevin',    'Brooks',   'PA', 52.6, 44, 'verified', false, $tirz,      'red'],     // BMI critical
            ['Ashley',   'Nguyen',   'CA', 26.0, 17, 'failed',   false, $metformin, 'red'],     // minor + id failed
        ];

        $i = 0;
        foreach ($rows as [$first, $last, $state, $bmi, $age, $idStatus, $hold, $offering, $expected]) {
            $i++;
            $patient = Patient::updateOrCreate(
                ['email' => strtolower("$first.$last@example.test"), 'partner_id' => $partner->id],
                [
                    'first_name'         => $first,
                    'last_name'          => $last,
                    'state'              => $state,
                    'gender'             => $i % 2 ? 'female' : 'male',
                    'bmi'                => $bmi,
                    'age'                => $age,
                    'id_verified_status' => $idStatus,
                    'status'             => 'active',
                ]
            );

            $case = PatientCase::updateOrCreate(
                ['partner_id' => $partner->id, 'external_id' => 'TRIAGE-DEMO-' . $i],
                [
                    'patient_id'    => $patient->id,
                    'status'        => PatientCase::STATUS_WAITING,
                    'hold_status'   => $hold,
                    'patient_state' => $state,
                    'is_chargeable' => true,
                ]
            );

            if ($offering) {
                CaseOffering::updateOrCreate(
                    ['case_id' => $case->id, 'offering_id' => $offering->id],
                    ['status' => 'requested', 'quantity' => 1, 'price' => $offering->price ?? 0]
                );
            }

            $case->load(['patient', 'caseOfferings.offering']);
            $classifier->apply($case);
            $case->refresh();

            $flag = $case->triage === $expected ? 'ok' : "EXPECTED {$expected}";
            $this->command?->line("  {$first} {$last}: {$case->triage} [{$flag}]");
        }
    }
}
