<?php

namespace Database\Seeders;

use App\Models\OfferingCategory;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Refill General Check-In" questionnaire.
 *
 * This questionnaire is shown to clinicians when reviewing refill/check-in cases.
 * It collects: medication tolerance, weight change, new medications, new conditions,
 * and dose continuation preference — each with a conditional free-text follow-up.
 *
 * After creating the questionnaire the seeder also wires it as the default check-in
 * questionnaire for ALL offering categories that do not already have one configured,
 * so refill cases across every program automatically display it in the prescribe view.
 *
 * PURPOSE:    check_in  (recognised by the platform's refill resolution logic)
 * MODE:       single    (one step — no multi-step wizard)
 * IDEMPOTENT: yes — re-running is safe; existing records are skipped, never duplicated.
 *
 * Run:  php artisan db:seed --class=RefillGeneralQuestionnaireSeeder
 */
class RefillGeneralQuestionnaireSeeder extends Seeder
{
    private const QUESTIONNAIRE_NAME = 'Refill General Check-In';

    public function run(): void
    {
        // ── Guard ─────────────────────────────────────────────────────────────
        if (Questionnaire::where('name', self::QUESTIONNAIRE_NAME)->exists()) {
            $this->command->info(self::QUESTIONNAIRE_NAME . ' already seeded — skipping questionnaire creation.');
            $this->wireToCategories();
            return;
        }

        // ── Create questionnaire ──────────────────────────────────────────────
        $q = Questionnaire::create([
            'name'        => self::QUESTIONNAIRE_NAME,
            'description' => 'General refill check-in covering medication tolerance, weight changes, new medications/conditions, and dose continuation preference.',
            'mode'        => 'single',
            'purpose'     => 'check_in',
            'is_active'   => true,
        ]);

        $this->command->info('  created  questionnaire [' . self::QUESTIONNAIRE_NAME . "] (id={$q->id})");

        // ── Q1: Medication tolerance ──────────────────────────────────────────
        $qTolerance = $q->questions()->create([
            'key'         => 'medication_tolerance',
            'question'    => 'How are you tolerating your current medication?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 10,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Well — no issues',                        'value' => 'well'],
                ['label' => 'Mild side effects — manageable',          'value' => 'mild_side_effects'],
                ['label' => 'Moderate side effects — affecting daily life', 'value' => 'moderate_side_effects'],
                ['label' => 'I stopped taking it',                     'value' => 'stopped'],
            ],
        ]);

        // Conditional follow-up: shown when any issue is reported (not "well")
        $q->questions()->create([
            'key'                    => 'side_effects',
            'question'               => 'Please describe your side effects or the reason you stopped.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Nausea in the mornings, resolved after 2 weeks',
            'is_required'            => true,
            'sort_order'             => 11,
            'step_number'            => 1,
            'depends_on_question_id' => $qTolerance->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'well',
        ]);

        // ── Q2: Weight change ─────────────────────────────────────────────────
        $qWeight = $q->questions()->create([
            'key'         => 'weight_change',
            'question'    => 'Have you noticed any changes in your weight since your last visit?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 20,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes — I have lost weight',  'value' => 'lost_weight'],
                ['label' => 'Yes — I have gained weight', 'value' => 'gained_weight'],
                ['label' => 'No change',                  'value' => 'no_change'],
            ],
        ]);

        // Conditional follow-up: shown when there was a change (not "no_change")
        $q->questions()->create([
            'key'                    => 'weight_change_details',
            'question'               => 'Please share more about your weight changes (approximate amount, timeframe).',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Lost approximately 8 lbs over the past 3 months',
            'is_required'            => true,
            'sort_order'             => 21,
            'step_number'            => 1,
            'depends_on_question_id' => $qWeight->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'no_change',
        ]);

        // ── Q3: New medications or supplements ────────────────────────────────
        $qMeds = $q->questions()->create([
            'key'         => 'new_medications',
            'question'    => 'Are you currently taking any new medications or supplements since your last visit?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 30,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        $q->questions()->create([
            'key'                    => 'new_medications_list',
            'question'               => 'Please list your new medications or supplements and dosages.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Vitamin D 2000 IU daily, Metformin 500mg twice daily',
            'is_required'            => true,
            'sort_order'             => 31,
            'step_number'            => 1,
            'depends_on_question_id' => $qMeds->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // ── Q4: New medical conditions ────────────────────────────────────────
        $qConditions = $q->questions()->create([
            'key'         => 'new_conditions',
            'question'    => 'Have you had any new medical conditions, diagnoses, or injuries since your last visit?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 40,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        $q->questions()->create([
            'key'                    => 'new_conditions_details',
            'question'               => 'Please describe your new medical condition(s), diagnosis, or injury.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Diagnosed with hypertension, prescribed Lisinopril 10mg',
            'is_required'            => true,
            'sort_order'             => 41,
            'step_number'            => 1,
            'depends_on_question_id' => $qConditions->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // ── Q5: Dose continuation preference ─────────────────────────────────
        $q->questions()->create([
            'key'         => 'dose_continuation',
            'question'    => 'How would you like to continue your treatment?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 50,
            'step_number' => 1,
            'options'     => [
                ['label' => 'Continue at the same dose',                                           'value' => 'same_dose'],
                ['label' => 'Increase dose (if a higher one is available)',                        'value' => 'increase_dose'],
                ['label' => 'Decrease dose',                                                        'value' => 'decrease_dose'],
                ['label' => 'I would like to stop treatment — please discuss with my provider',    'value' => 'stop_treatment'],
            ],
        ]);

        // ── Optional: anything else ───────────────────────────────────────────
        $q->questions()->create([
            'key'         => 'additional_notes',
            'question'    => 'Is there anything else you would like to share with your provider?',
            'type'        => 'text',
            'placeholder' => 'Optional — anything your provider should know for this visit',
            'is_required' => false,
            'sort_order'  => 60,
            'step_number' => 1,
        ]);

        $this->command->info('  created  ' . $q->questions()->count() . ' questions (5 main + 4 conditional + 1 optional)');

        // ── Wire to offering categories ───────────────────────────────────────
        $this->wireToCategories($q);
    }

    /**
     * Set this questionnaire as the default check-in questionnaire on ALL active
     * offering categories that do not already have one configured.
     *
     * This is a general refill questionnaire — not program-specific — so every
     * category gets it by default. Categories that already have a dedicated
     * check-in questionnaire are left untouched.
     */
    private function wireToCategories(?Questionnaire $q = null): void
    {
        if ($q === null) {
            $q = Questionnaire::where('name', self::QUESTIONNAIRE_NAME)->first();
            if (!$q) return;
        }

        $categories = OfferingCategory::all();

        if ($categories->isEmpty()) {
            $this->command->warn('  skipped  no offering categories found — run offering seeders first.');
            return;
        }

        foreach ($categories as $category) {
            if ($category->check_in_questionnaire_id && $category->check_in_questionnaire_id !== $q->id) {
                $this->command->line("  skipped  category [{$category->name}] already has a check-in questionnaire (id={$category->check_in_questionnaire_id}) — not overwriting.");
                continue;
            }

            if ($category->check_in_questionnaire_id === $q->id) {
                $this->command->line("  skipped  category [{$category->name}] already wired to this questionnaire.");
                continue;
            }

            $category->update(['check_in_questionnaire_id' => $q->id]);
            $this->command->info("  wired    category [{$category->name}] → check-in questionnaire [{$q->name}] (id={$q->id})");
        }
    }
}
