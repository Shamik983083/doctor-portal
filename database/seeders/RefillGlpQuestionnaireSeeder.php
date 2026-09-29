<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Refill GLP Check-In" questionnaire — GLP-1 / GLP-1/GIP programmes only.
 *
 * Questions collected:
 *   1. Current weight (open entry)
 *   2. Which GLP-1 medication? → drug-specific dose conditional (Q3a / Q3b)
 *   3a. Last dose — Semaglutide (shown only when Q2 = semaglutide)
 *   3b. Last dose — Tirzepatide (shown only when Q2 = tirzepatide)
 *   4. When was your last dose? (0-7 / 8-14 / 15-30 / 30+ days)
 *   5. Side effects in last month? (Yes / No) → conditional multi-select
 *   6. Which side effects? (Nausea / Constipation / Diarrhea / Hair Loss / Other)
 *        → conditional text for "Other"
 *   7. Satisfied with rate of weight loss? (Yes / No) → conditional text if No
 *   8. Requests regarding medication dosage? (Yes / No) → conditional text if Yes
 *
 * Weight chart:
 *   The tenant portal compiles the patient's weight history (starting weight +
 *   all prior check-ins + current entry) and passes it to the doctor portal via
 *   case metadata["weight_history"]. The prescribe view renders it as a line chart
 *   when that data is present.
 *
 * PURPOSE:    check_in   (refill resolution logic indexes this first for refill cases)
 * MODE:       single
 * WIRED TO:  "GLP" category only  (overrides the general Refill General Check-In for GLP)
 * IDEMPOTENT: yes — re-running is safe.
 *
 * Run:  php artisan db:seed --class=RefillGlpQuestionnaireSeeder
 */
class RefillGlpQuestionnaireSeeder extends Seeder
{
    private const QUESTIONNAIRE_NAME = 'Refill GLP Check-In';
    private const TARGET_CATEGORY    = 'GLP';

    // Semaglutide dosing ladder
    private const SEMA_DOSES = [
        ['label' => 'Semaglutide 0.25 mg', 'value' => 'sema_0_25'],
        ['label' => 'Semaglutide 0.50 mg', 'value' => 'sema_0_50'],
        ['label' => 'Semaglutide 1 mg',    'value' => 'sema_1'],
        ['label' => 'Semaglutide 1.5 mg',  'value' => 'sema_1_5'],
        ['label' => 'Semaglutide 2 mg',    'value' => 'sema_2'],
        ['label' => 'Semaglutide 2.5 mg',  'value' => 'sema_2_5'],
    ];

    // Tirzepatide dosing ladder
    private const TIRZE_DOSES = [
        ['label' => 'Tirzepatide 2.5 mg',  'value' => 'tirze_2_5'],
        ['label' => 'Tirzepatide 5 mg',    'value' => 'tirze_5'],
        ['label' => 'Tirzepatide 7.5 mg',  'value' => 'tirze_7_5'],
        ['label' => 'Tirzepatide 10 mg',   'value' => 'tirze_10'],
        ['label' => 'Tirzepatide 12.5 mg', 'value' => 'tirze_12_5'],
        ['label' => 'Tirzepatide 15 mg',   'value' => 'tirze_15'],
    ];

    public function run(): void
    {
        // ── Guard ─────────────────────────────────────────────────────────────
        if (Questionnaire::where('name', self::QUESTIONNAIRE_NAME)->exists()) {
            $this->command->info(self::QUESTIONNAIRE_NAME . ' already seeded — skipping creation.');
            $this->wireToGlp();
            return;
        }

        // ── Create questionnaire ──────────────────────────────────────────────
        $q = Questionnaire::create([
            'name'        => self::QUESTIONNAIRE_NAME,
            'description' => 'GLP-1/GIP refill check-in: current weight, drug-specific last dose, side effects, weight loss satisfaction, and dosage requests.',
            'mode'        => 'single',
            'purpose'     => 'check_in',
            'is_active'   => true,
        ]);

        $this->command->info('  created  questionnaire [' . self::QUESTIONNAIRE_NAME . "] (id={$q->id})");

        // ── Q1: Current weight ────────────────────────────────────────────────
        $q->questions()->create([
            'key'         => 'current_weight',
            'question'    => 'What is your current weight? (lbs)',
            'type'        => 'text',
            'placeholder' => 'e.g. 210',
            'is_required' => true,
            'sort_order'  => 10,
            'step_number' => 1,
        ]);

        // ── Q2: Which GLP-1 medication are you taking? ────────────────────────
        $qDrug = $q->questions()->create([
            'key'         => 'glp_medication_type',
            'question'    => 'Which GLP-1 medication are you currently taking?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 20,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Semaglutide (Ozempic / Wegovy / compounded)',   'value' => 'semaglutide'],
                ['label' => 'Tirzepatide (Mounjaro / Zepbound / compounded)', 'value' => 'tirzepatide'],
            ],
        ]);

        // ── Q3a: Last dose — Semaglutide (conditional on Q2 = semaglutide) ───
        $q->questions()->create([
            'key'                    => 'last_dose_semaglutide',
            'question'               => 'What was your last dose of Semaglutide?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 30,
            'step_number'            => 1,
            'options'                => self::SEMA_DOSES,
            'depends_on_question_id' => $qDrug->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'semaglutide',
        ]);

        // ── Q3b: Last dose — Tirzepatide (conditional on Q2 = tirzepatide) ───
        $q->questions()->create([
            'key'                    => 'last_dose_tirzepatide',
            'question'               => 'What was your last dose of Tirzepatide?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 31,
            'step_number'            => 1,
            'options'                => self::TIRZE_DOSES,
            'depends_on_question_id' => $qDrug->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'tirzepatide',
        ]);

        // ── Q4: When was your last dose? ──────────────────────────────────────
        $q->questions()->create([
            'key'         => 'last_dose_date',
            'question'    => 'When did you take that last dose?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 40,
            'step_number' => 1,
            'options'     => [
                ['label' => '0–7 days ago',   'value' => '0_7_days'],
                ['label' => '8–14 days ago',  'value' => '8_14_days'],
                ['label' => '15–30 days ago', 'value' => '15_30_days'],
                ['label' => '30+ days ago',   'value' => '30_plus_days'],
            ],
        ]);

        // ── Q5: Side effects in last month? ──────────────────────────────────
        $qSideEffects = $q->questions()->create([
            'key'         => 'side_effects_experienced',
            'question'    => 'In the last month, have you experienced any side effects from your current dose?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 50,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // ── Q6: Which side effects? (multi-select, conditional on Q5 = yes) ───
        $qSideEffectList = $q->questions()->create([
            'key'                    => 'side_effects_list',
            'question'               => 'Which side effects have you experienced? (select all that apply)',
            'type'                   => 'multi',
            'is_required'            => true,
            'sort_order'             => 51,
            'step_number'            => 1,
            'options'                => [
                ['label' => 'Nausea',       'value' => 'nausea'],
                ['label' => 'Constipation', 'value' => 'constipation'],
                ['label' => 'Diarrhea',     'value' => 'diarrhea'],
                ['label' => 'Hair Loss',    'value' => 'hair_loss'],
                ['label' => 'Other',        'value' => 'other'],
            ],
            'depends_on_question_id' => $qSideEffects->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // ── Q6b: Other side effects — text (conditional on Q6 contains "other") ─
        $q->questions()->create([
            'key'                    => 'side_effects_other_details',
            'question'               => 'Please describe your other side effects.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Fatigue, mild headaches',
            'is_required'            => true,
            'sort_order'             => 52,
            'step_number'            => 1,
            'depends_on_question_id' => $qSideEffectList->id,
            'depends_on_operator'    => 'contains',
            'depends_on_value'       => 'other',
        ]);

        // ── Q7: Satisfied with rate of weight loss? ───────────────────────────
        $qSatisfied = $q->questions()->create([
            'key'         => 'weight_loss_satisfied',
            'question'    => 'Are you satisfied with your current rate of weight loss?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 60,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        $q->questions()->create([
            'key'                    => 'weight_loss_dissatisfied_reason',
            'question'               => 'Please explain why you are not satisfied with your rate of weight loss.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Only lost 2 lbs in the past month, expected more progress',
            'is_required'            => true,
            'sort_order'             => 61,
            'step_number'            => 1,
            'depends_on_question_id' => $qSatisfied->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'no',
        ]);

        // ── Q8: Dosage requests? ──────────────────────────────────────────────
        $qDosageRequest = $q->questions()->create([
            'key'         => 'dosage_request',
            'question'    => 'Do you have any requests regarding your medication dosage?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 70,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        $q->questions()->create([
            'key'                    => 'dosage_request_details',
            'question'               => 'Please describe your dosage request.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. I would like to increase to the next dose level',
            'is_required'            => true,
            'sort_order'             => 71,
            'step_number'            => 1,
            'depends_on_question_id' => $qDosageRequest->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        $count = $q->questions()->count();
        $this->command->info("  created  {$count} questions (8 main + 5 conditional)");

        $this->wireToGlp($q);
        $this->attachGeneralToGlpOfferings();
    }

    /**
     * Wire this questionnaire to the "GLP" offering category.
     * Always overwrites — this is a more specific questionnaire than the
     * general Refill General Check-In and should replace it for GLP.
     */
    private function wireToGlp(?Questionnaire $q = null): void
    {
        if ($q === null) {
            $q = Questionnaire::where('name', self::QUESTIONNAIRE_NAME)->first();
            if (!$q) return;
        }

        $category = OfferingCategory::where('name', self::TARGET_CATEGORY)->first();

        if (!$category) {
            $this->command->warn('  skipped  GLP category not found — run OfferingCategoriesSeeder first.');
            return;
        }

        if ($category->check_in_questionnaire_id === $q->id) {
            $this->command->line('  skipped  GLP category already wired to this questionnaire.');
            return;
        }

        $previous = $category->check_in_questionnaire_id
            ? " (replacing id={$category->check_in_questionnaire_id})"
            : '';

        $category->update(['check_in_questionnaire_id' => $q->id]);
        $this->command->info("  wired    category [GLP] → [{$q->name}] (id={$q->id}){$previous}");
    }

    /**
     * Attach "Refill General Check-In" to every GLP offering via the
     * offering_questionnaire pivot so both check-in questionnaires are
     * indexed when a GLP refill case is processed.
     */
    private function attachGeneralToGlpOfferings(): void
    {
        $generalQ = Questionnaire::where('name', 'Refill General Check-In')->first();

        if (!$generalQ) {
            $this->command->warn('  skipped  "Refill General Check-In" not found — run RefillGeneralQuestionnaireSeeder first.');
            return;
        }

        $category = OfferingCategory::where('name', self::TARGET_CATEGORY)->first();

        if (!$category) {
            $this->command->warn('  skipped  GLP category not found — cannot attach general questionnaire to GLP offerings.');
            return;
        }

        $offerings = Offering::where('offering_category_id', $category->id)->get();

        if ($offerings->isEmpty()) {
            $this->command->warn('  skipped  no offerings found in GLP category.');
            return;
        }

        $attached = 0;
        foreach ($offerings as $offering) {
            $offering->questionnaires()->syncWithoutDetaching([
                $generalQ->id => ['is_required' => false, 'sort_order' => 99],
            ]);
            $attached++;
        }

        $this->command->info("  attached [Refill General Check-In] (id={$generalQ->id}) to {$attached} GLP offering(s) via pivot.");
    }
}
