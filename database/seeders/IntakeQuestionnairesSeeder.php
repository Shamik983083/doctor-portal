<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use Illuminate\Database\Seeder;

class IntakeQuestionnairesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMWLWeightLoss();
        $this->seedAntiAging();
    }

    // ────────────────────────────────────────────────────────────────────────────
    // SHARED: Standard Intake questions — added to the top of every program
    // questionnaire so each is self-contained.
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Seed the shared standard-intake questions into $q on the given step.
     * Sort orders start at $sortStart and increment by 10 (sub-items +1).
     */
    private function seedStandardIntakeQuestions(Questionnaire $q, int $step = 1, int $sortStart = 10): void
    {
        // Q — Pregnancy / breastfeeding
        $q->questions()->create([
            'key'         => 'pregnant_breastfeeding',
            'question'    => 'If female — are you currently pregnant or breastfeeding?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes', 'disqualifies' => true],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Blood pressure range
        $q->questions()->create([
            'key'         => 'blood_pressure_range',
            'question'    => 'What is your blood pressure range?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 10,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Normal (less than 120/80)',          'value' => 'normal'],
                ['label' => 'Elevated (120–129 / less than 80)', 'value' => 'elevated'],
                ['label' => 'High Stage 1 (130–139 / 80–89)',    'value' => 'high_stage_1'],
                ['label' => 'High Stage 2 (140+ / 90+)',         'value' => 'high_stage_2'],
                ['label' => "I don't know",                      'value' => 'unknown'],
            ],
        ]);

        // Q — Prescription medications
        $qMeds = $q->questions()->create([
            'key'         => 'prescription_medications',
            'question'    => 'Are you currently taking any prescription medications?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 20,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Yes – Please list the names and dosages',       'value' => 'yes'],
                ['label' => 'No – I affirm I am not taking any medications', 'value' => 'no'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'prescription_medications_list',
            'question'               => 'Please list your current medications and dosages.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Metformin 500mg twice daily, Lisinopril 10mg once daily',
            'is_required'            => true,
            'sort_order'             => $sortStart + 21,
            'step_number'            => $step,
            'depends_on_question_id' => $qMeds->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // Q — Medication allergies
        $qAllergies = $q->questions()->create([
            'key'         => 'medication_allergies',
            'question'    => 'Do you have any medication allergies?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 30,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Yes – Please list your allergies and any known reactions', 'value' => 'yes'],
                ['label' => 'No – I affirm I have no known drug allergies',             'value' => 'no'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'medication_allergies_list',
            'question'               => 'Please list your allergies and any known reactions.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Penicillin – hives and rash',
            'is_required'            => true,
            'sort_order'             => $sortStart + 31,
            'step_number'            => $step,
            'depends_on_question_id' => $qAllergies->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // Q — Medical conditions (general)
        $qConditions = $q->questions()->create([
            'key'         => 'medical_conditions',
            'question'    => 'Do you have any medical conditions?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 40,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Yes – Please list your medical conditions',        'value' => 'yes'],
                ['label' => 'No – I affirm I have no known medical conditions', 'value' => 'no'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'medical_conditions_list',
            'question'               => 'Please list your medical conditions.',
            'type'                   => 'text',
            'placeholder'            => 'e.g. Type 2 Diabetes, Hypertension',
            'is_required'            => true,
            'sort_order'             => $sortStart + 41,
            'step_number'            => $step,
            'depends_on_question_id' => $qConditions->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // Q — Injuries / surgeries
        $qInjuries = $q->questions()->create([
            'key'         => 'injuries_surgeries',
            'question'    => 'Have you had any injuries or surgeries within the last 6 months?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 50,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'injuries_surgeries_details',
            'question'               => 'Please provide details of the injuries or surgeries.',
            'type'                   => 'text',
            'placeholder'            => 'Please provide details of the injuries or surgeries',
            'is_required'            => true,
            'sort_order'             => $sortStart + 51,
            'step_number'            => $step,
            'depends_on_question_id' => $qInjuries->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // Q — Physical activity
        $q->questions()->create([
            'key'         => 'physical_activity',
            'question'    => 'How physically active are you?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 60,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Sedentary',              'value' => 'sedentary'],
                ['label' => 'Somewhat Active',        'value' => 'somewhat_active'],
                ['label' => 'Active but not Athletic', 'value' => 'active_not_athletic'],
                ['label' => 'Athletic',               'value' => 'athletic'],
                ['label' => 'Competitive/Biohacker',  'value' => 'competitive_biohacker'],
            ],
        ]);

        // Q — Last medical evaluation
        $q->questions()->create([
            'key'         => 'last_medical_evaluation',
            'question'    => 'When was the last time you had an in-person medical evaluation?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 70,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Less than a year ago',  'value' => 'less_than_1_year'],
                ['label' => '1 to 2 years ago',      'value' => '1_to_2_years'],
                ['label' => 'More than 2 years ago', 'value' => 'more_than_2_years'],
            ],
        ]);

        // Q — Last lab tests
        $q->questions()->create([
            'key'         => 'last_lab_tests',
            'question'    => 'When were your last lab tests performed?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 71,
            'step_number' => $step,
            'options'     => [
                ['label' => 'Less than a year ago',  'value' => 'less_than_1_year'],
                ['label' => '1 to 2 years ago',      'value' => '1_to_2_years'],
                ['label' => 'More than 2 years ago', 'value' => 'more_than_2_years'],
            ],
        ]);

        // Q — First message to doctor
        $q->questions()->create([
            'key'         => 'first_message_to_doctor',
            'question'    => 'Please provide your first message to the doctor here (anything else you want them to know):',
            'type'        => 'text',
            'placeholder' => 'Optional – share anything else you would like your doctor to know',
            'is_required' => false,
            'sort_order'  => $sortStart + 80,
            'step_number' => $step,
        ]);

        // Q — General Informed Consent (Telehealth)
        $q->questions()->create([
            'key'         => 'telehealth_informed_consent',
            'question'    => $this->telehealthConsentText(),
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => $sortStart + 90,
            'step_number' => $step,
            'options'     => [
                ['label' => 'I confirm I have read, understand, and agree to this consent.',   'value' => 'agree'],
                ['label' => 'I have read the above information and I do not wish to continue', 'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // MWL – WEIGHT LOSS  (mode: multi)
    //   Step 1: Standard Intake questions
    //   Step 2: Program-specific medical intake
    //   Step 3: Consents
    // ────────────────────────────────────────────────────────────────────────────

    private function seedMWLWeightLoss(): void
    {
        if (Questionnaire::where('name', 'MWL – Weight Loss')->exists()) {
            $this->command->info('MWL – Weight Loss already seeded — skipping.');
            return;
        }

        $q = Questionnaire::create([
            'name'        => 'MWL – Weight Loss',
            'description' => 'Weight loss program intake. Step 1: standard intake. Step 2: program-specific medical history & GLP-1 status. Step 3: consents.',
            'mode'        => 'multi',
            'is_active'   => true,
        ]);

        // ── STEP 1: Standard Intake questions ──────────────────────────────
        $this->seedStandardIntakeQuestions($q, step: 1, sortStart: 10);

        // ── STEP 2: Program-specific medical intake ────────────────────────

        // Q — Medical conditions checklist
        $qMedConditions = $q->questions()->create([
            'key'         => 'mwl_medical_conditions',
            'question'    => 'Please check all current or past medical conditions that you have had.',
            'type'        => 'multi',
            'is_required' => true,
            'sort_order'  => 10,
            'step_number' => 2,
            'placeholder' => 'Select all that apply. Selecting gallbladder or thyroid conditions will display an additional consent on the next step.',
            'options'     => [
                // ── Disqualifying ────────────────────────────────────────────
                ['label' => 'Gastroparesis (Paralysis of your intestines)',                               'value' => 'gastroparesis',         'disqualifies' => true],
                ['label' => 'Triglycerides over 600 at any point',                                       'value' => 'triglycerides_600',     'disqualifies' => true],
                ['label' => 'Pancreatic cancer',                                                         'value' => 'pancreatic_cancer',     'disqualifies' => true],
                ['label' => 'Pancreatitis',                                                              'value' => 'pancreatitis',          'disqualifies' => true],
                ['label' => 'Type 1 Diabetes',                                                           'value' => 'type1_diabetes',        'disqualifies' => true],
                ['label' => 'Hypoglycemia (low blood sugar)',                                            'value' => 'hypoglycemia',          'disqualifies' => true],
                ['label' => 'Insulin-dependent diabetes',                                                'value' => 'insulin_dependent',     'disqualifies' => true],
                ['label' => 'Thyroid cancer',                                                            'value' => 'thyroid_cancer',        'disqualifies' => true],
                ['label' => 'Family history of thyroid cancer',                                          'value' => 'family_thyroid_cancer', 'disqualifies' => true],
                ['label' => 'Personal or family history of Multiple Endocrine Neoplasia (MEN-2) syndrome', 'value' => 'men2_syndrome',      'disqualifies' => true],
                ['label' => 'Anorexia or bulimia',                                                       'value' => 'eating_disorder',      'disqualifies' => true],
                ['label' => 'Current symptomatic gallstones',                                            'value' => 'symptomatic_gallstones', 'disqualifies' => true],
                ['label' => 'Liver failure / liver cirrhosis',                                           'value' => 'liver_failure',         'disqualifies' => true],
                ['label' => 'Chronic Kidney Disease Stage 3b or greater',                                'value' => 'ckd_stage3b',           'disqualifies' => true],
                ['label' => 'Syndrome of Inappropriate Antidiuretic Hormone (SIADH)',                    'value' => 'siadh',                 'disqualifies' => true],
                // ── Non-disqualifying (trigger extra consents where noted) ──
                ['label' => 'Gallbladder disease or past removal of your gallbladder',                   'value' => 'gallbladder_disease'],
                ['label' => 'Hypertension (high blood pressure)',                                        'value' => 'hypertension'],
                ['label' => 'Dyslipidemia (high cholesterol or triglycerides)',                          'value' => 'dyslipidemia'],
                ['label' => 'Sleep apnea',                                                               'value' => 'sleep_apnea'],
                ['label' => 'Osteoarthritis',                                                            'value' => 'osteoarthritis'],
                ['label' => 'Mobility issues which are impacted by body weight',                         'value' => 'mobility_issues'],
                ['label' => 'Gastroesophageal reflux disease (GERD) related to body weight',             'value' => 'gerd'],
                ['label' => 'Polycystic Ovary Syndrome with insulin resistance',                         'value' => 'pcos_insulin_resistance'],
                ['label' => 'Liver disease or conditions affecting the liver (e.g. NAFLD)',               'value' => 'liver_disease'],
                ['label' => 'Heart disease or conditions affecting the heart',                           'value' => 'heart_disease'],
                ['label' => 'Metabolic Syndrome',                                                        'value' => 'metabolic_syndrome'],
                ['label' => 'Hypothyroidism, Hyperthyroidism, or Thyroid Issues',                        'value' => 'thyroid_issues'],
                ['label' => 'Prediabetes',                                                               'value' => 'prediabetes'],
                ['label' => 'Type 2 Diabetes',                                                           'value' => 'type2_diabetes'],
                ['label' => 'None of the above',                                                         'value' => 'none'],
            ],
        ]);

        // Q — Gastric bypass
        $q->questions()->create([
            'key'         => 'mwl_gastric_bypass',
            'question'    => 'Have you had a gastric bypass in the past 6 months?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 20,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes', 'disqualifies' => true],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — GLP-1 branded allergy
        $q->questions()->create([
            'key'         => 'mwl_glp1_brand_allergy',
            'question'    => 'Are you allergic to any of the following?',
            'type'        => 'multi',
            'is_required' => true,
            'sort_order'  => 30,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Ozempic',           'value' => 'ozempic',   'disqualifies' => true],
                ['label' => 'Mounjaro',          'value' => 'mounjaro',  'disqualifies' => true],
                ['label' => 'Wegovy',            'value' => 'wegovy',    'disqualifies' => true],
                ['label' => 'Zepbound',          'value' => 'zepbound',  'disqualifies' => true],
                ['label' => 'Saxenda',           'value' => 'saxenda',   'disqualifies' => true],
                ['label' => 'Trulicity',         'value' => 'trulicity', 'disqualifies' => true],
                ['label' => 'None of the above', 'value' => 'none'],
            ],
        ]);

        // Q — Current / recent GLP-1 medication
        $qCurrentGlp1 = $q->questions()->create([
            'key'         => 'mwl_current_glp1',
            'question'    => 'Are you currently, or have you in the past two months, taken any of the following medications?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 40,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Semaglutide (Ozempic, Wegovy)',    'value' => 'semaglutide'],
                ['label' => 'Tirzepatide (Zepbound, Mounjaro)', 'value' => 'tirzepatide'],
                ['label' => 'None of the above',                'value' => 'none'],
            ],
        ]);

        // Q — Side effects (shown if Sema or Tirze selected)
        $q->questions()->create([
            'key'                    => 'mwl_glp1_side_effects',
            'question'               => 'Have you experienced side effects from your current medication?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 41,
            'step_number'            => 2,
            'depends_on_question_id' => $qCurrentGlp1->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'none',
            'options'                => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Current dose match
        $q->questions()->create([
            'key'                    => 'mwl_current_dose',
            'question'               => 'Which medication and dose most closely matches your most recent dose?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 42,
            'step_number'            => 2,
            'depends_on_question_id' => $qCurrentGlp1->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'none',
            'options'                => [
                ['label' => 'Semaglutide 0.25mg',  'value' => 'sema_0_25'],
                ['label' => 'Semaglutide 0.5mg',   'value' => 'sema_0_5'],
                ['label' => 'Semaglutide 1mg',     'value' => 'sema_1'],
                ['label' => 'Semaglutide 2mg',     'value' => 'sema_2'],
                ['label' => 'Semaglutide 2.5mg',   'value' => 'sema_2_5'],
                ['label' => 'Tirzepatide 2.5mg',   'value' => 'tirze_2_5'],
                ['label' => 'Tirzepatide 5mg',     'value' => 'tirze_5'],
                ['label' => 'Tirzepatide 7.5mg',   'value' => 'tirze_7_5'],
                ['label' => 'Tirzepatide 10mg',    'value' => 'tirze_10'],
                ['label' => 'Tirzepatide 12.5mg',  'value' => 'tirze_12_5'],
                ['label' => 'Tirzepatide 15mg',    'value' => 'tirze_15'],
            ],
        ]);

        // Q — Treatment continuation preference
        $q->questions()->create([
            'key'                    => 'mwl_treatment_continuation',
            'question'               => 'How would you like to continue your treatment?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 43,
            'step_number'            => 2,
            'depends_on_question_id' => $qCurrentGlp1->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'none',
            'options'                => [
                ['label' => 'Stay at the same dose or equivalent dose',                                                                   'value' => 'same_dose'],
                ['label' => 'Increase the dose if a higher one is available, or continue with my current dose if it is at the maximum',   'value' => 'increase_dose'],
                ['label' => 'Decrease dose',                                                                                              'value' => 'decrease_dose'],
            ],
        ]);

        // Q — Has prescription picture?
        $qHasPic = $q->questions()->create([
            'key'                    => 'mwl_has_prescription_pic',
            'question'               => 'Do you have a picture of your current prescription? We need this photograph in order to validate your current dosage.',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 44,
            'step_number'            => 2,
            'depends_on_question_id' => $qCurrentGlp1->id,
            'depends_on_operator'    => 'not_equals',
            'depends_on_value'       => 'none',
            'options'                => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Upload prescription picture
        $q->questions()->create([
            'key'                    => 'mwl_prescription_pic_upload',
            'question'               => 'Please upload a picture of the prescription or bottle of your current GLP-1/GIP medication.',
            'type'                   => 'file',
            'placeholder'            => 'Upload your prescription or medication bottle image',
            'is_required'            => true,
            'sort_order'             => 45,
            'step_number'            => 2,
            'depends_on_question_id' => $qHasPic->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // ── STEP 3: Consents ───────────────────────────────────────────────

        // Gallbladder Disease Consent (conditional)
        $q->questions()->create([
            'key'                    => 'mwl_gallbladder_consent',
            'question'               => $this->gallbladderConsentText(),
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 10,
            'step_number'            => 3,
            'depends_on_question_id' => $qMedConditions->id,
            'depends_on_operator'    => 'contains',
            'depends_on_value'       => 'gallbladder_disease',
            'options'                => [
                ['label' => 'I understand and wish to proceed', 'value' => 'agree'],
                ['label' => 'I do not wish to continue',        'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);

        // Thyroid Consent (conditional)
        $q->questions()->create([
            'key'                    => 'mwl_thyroid_consent',
            'question'               => $this->thyroidConsentText(),
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 20,
            'step_number'            => 3,
            'depends_on_question_id' => $qMedConditions->id,
            'depends_on_operator'    => 'contains',
            'depends_on_value'       => 'thyroid_issues',
            'options'                => [
                ['label' => 'I understand and wish to proceed', 'value' => 'agree'],
                ['label' => 'I do not wish to continue',        'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);

        // Consent (Truthfulness)
        $q->questions()->create([
            'key'         => 'mwl_consent_truthfulness',
            'question'    => $this->mwlTruthfulnessConsentText(),
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 30,
            'step_number' => 3,
            'options'     => [
                ['label' => 'I have read the above information and I consent and wish to move forward', 'value' => 'agree'],
                ['label' => 'I have read the above information and I do not wish to continue',          'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);

        // Consent (GLP-1 and GLP-1/GIP)
        $q->questions()->create([
            'key'         => 'mwl_consent_glp1',
            'question'    => $this->glp1ConsentText(),
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 40,
            'step_number' => 3,
            'options'     => [
                ['label' => 'I have read and understand the above information and I wish to continue', 'value' => 'agree'],
                ['label' => 'I have read the above information and I do not wish to continue',         'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // ANTI-AGING  (mode: multi)
    //   Step 1: Standard Intake questions
    //   Step 2: Program-specific medical intake
    //   Step 3: Consents
    // ────────────────────────────────────────────────────────────────────────────

    private function seedAntiAging(): void
    {
        if (Questionnaire::where('name', 'Anti-Aging')->exists()) {
            $this->command->info('Anti-Aging already seeded — skipping.');
            return;
        }

        $q = Questionnaire::create([
            'name'        => 'Anti-Aging',
            'description' => 'Anti-aging program intake. Step 1: standard intake. Step 2: program-specific medical history & prior treatments. Step 3: consents.',
            'mode'        => 'multi',
            'is_active'   => true,
        ]);

        // ── STEP 1: Standard Intake questions ──────────────────────────────
        $this->seedStandardIntakeQuestions($q, step: 1, sortStart: 10);

        // ── STEP 2: Program-specific medical intake ────────────────────────

        // Q — Primary reason for medication
        $qPrimaryReason = $q->questions()->create([
            'key'         => 'aa_primary_reason',
            'question'    => 'What is your primary reason for requesting this medication?',
            'type'        => 'multi',
            'is_required' => true,
            'sort_order'  => 10,
            'step_number' => 2,
            'options'     => [
                ['label' => 'General wellness',     'value' => 'general_wellness'],
                ['label' => 'Anti-Aging',           'value' => 'anti_aging'],
                ['label' => 'Antioxidant benefits', 'value' => 'antioxidant'],
                ['label' => 'Boost energy',         'value' => 'boost_energy'],
                ['label' => 'Brighten skin',        'value' => 'brighten_skin'],
                ['label' => 'Detoxification',       'value' => 'detox'],
                ['label' => 'Immune support',       'value' => 'immune_support'],
                ['label' => 'Other',                'value' => 'other'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'aa_primary_reason_other',
            'question'               => 'Please describe your primary reason.',
            'type'                   => 'text',
            'placeholder'            => 'Please provide more details',
            'is_required'            => true,
            'sort_order'             => 11,
            'step_number'            => 2,
            'depends_on_question_id' => $qPrimaryReason->id,
            'depends_on_operator'    => 'contains',
            'depends_on_value'       => 'other',
        ]);

        // Q — Currently experiencing symptoms
        $qSymptoms = $q->questions()->create([
            'key'         => 'aa_current_symptoms',
            'question'    => 'Are you currently experiencing any of the following?',
            'type'        => 'multi',
            'is_required' => true,
            'sort_order'  => 20,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Fatigue or Low Energy',      'value' => 'fatigue'],
                ['label' => 'Memory Issues',              'value' => 'memory_issues'],
                ['label' => 'Difficulty Concentrating',   'value' => 'difficulty_concentrating'],
                ['label' => 'Depression or Anxiety',      'value' => 'depression_anxiety'],
                ['label' => 'Sleep Disorders',            'value' => 'sleep_disorders'],
                ['label' => 'Chronic Pain',               'value' => 'chronic_pain'],
                ['label' => 'Other',                      'value' => 'other'],
                ['label' => 'None of these apply to me', 'value' => 'none'],
            ],
        ]);
        $q->questions()->create([
            'key'                    => 'aa_current_symptoms_other',
            'question'               => 'Please describe your other symptoms.',
            'type'                   => 'text',
            'placeholder'            => 'Describe your other symptoms',
            'is_required'            => true,
            'sort_order'             => 21,
            'step_number'            => 2,
            'depends_on_question_id' => $qSymptoms->id,
            'depends_on_operator'    => 'contains',
            'depends_on_value'       => 'other',
        ]);

        // Q — Prior treatment with Metformin / MIC / B-12 / Glutathione / NAD
        $qPriorTreatment = $q->questions()->create([
            'key'         => 'aa_prior_treatment',
            'question'    => 'Have you ever received treatment before with Metformin, MIC, B-12, Glutathione, or NAD?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 30,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Adverse reactions to prior treatment
        $qPriorReactions = $q->questions()->create([
            'key'                    => 'aa_prior_treatment_reactions',
            'question'               => 'Did you experience any allergic or negative reactions to any of these?',
            'type'                   => 'choice',
            'is_required'            => true,
            'sort_order'             => 31,
            'step_number'            => 2,
            'depends_on_question_id' => $qPriorTreatment->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
            'options'                => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Reaction details
        $q->questions()->create([
            'key'                    => 'aa_prior_treatment_reaction_details',
            'question'               => 'Please tell us more about what was used, when this was used, and what side effect(s) you experienced.',
            'type'                   => 'text',
            'placeholder'            => 'Please tell us more about what was used, when this was used, and what side effect(s) you experienced.',
            'is_required'            => true,
            'sort_order'             => 32,
            'step_number'            => 2,
            'depends_on_question_id' => $qPriorReactions->id,
            'depends_on_operator'    => 'equals',
            'depends_on_value'       => 'yes',
        ]);

        // Q — G6PD / CKD / Liver cirrhosis
        $q->questions()->create([
            'key'         => 'aa_g6pd_ckd_liver',
            'question'    => 'Do you have a known history of glucose-6-phosphate dehydrogenase (G6PD) deficiency, chronic kidney disease (CKD) or liver cirrhosis?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 40,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes', 'disqualifies' => true],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // Q — Cancer / chemotherapy / radiation
        $q->questions()->create([
            'key'         => 'aa_cancer_treatment',
            'question'    => 'Are you currently undergoing treatment for cancer, including chemotherapy or radiation therapy?',
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 50,
            'step_number' => 2,
            'options'     => [
                ['label' => 'Yes', 'value' => 'yes', 'disqualifies' => true],
                ['label' => 'No',  'value' => 'no'],
            ],
        ]);

        // ── STEP 3: Consents ───────────────────────────────────────────────

        // Consent (Truthfulness – General Intake)
        $q->questions()->create([
            'key'         => 'aa_consent_truthfulness',
            'question'    => $this->aaTruthfulnessConsentText(),
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 10,
            'step_number' => 3,
            'options'     => [
                ['label' => 'I have read the above information and I do consent and wish to move forward', 'value' => 'agree'],
                ['label' => 'I have read the above information and I do not wish to continue',             'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);

        // Consent (Informed Consent Statement)
        $q->questions()->create([
            'key'         => 'aa_consent_informed',
            'question'    => $this->aaInformedConsentText(),
            'type'        => 'choice',
            'is_required' => true,
            'sort_order'  => 20,
            'step_number' => 3,
            'options'     => [
                ['label' => 'I have read the above information, I understand the risks and would like to proceed', 'value' => 'agree'],
                ['label' => 'I have read the above information and I do not wish to continue',                     'value' => 'disagree', 'disqualifies' => true],
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // CONSENT TEXT HELPERS
    // ────────────────────────────────────────────────────────────────────────────

    private function telehealthConsentText(): string
    {
        return 'TELEHEALTH INFORMED CONSENT & AUTHORIZATION FOR TREATMENT

1. Purpose of Telehealth Services
Telehealth involves the use of electronic communication technologies (such as video calls, phone calls, secure messaging, and electronic prescribing) to provide health care services remotely. This consent applies to all telehealth medical services provided by Invigorate Health, LLC, including evaluation, diagnosis, treatment, and medication management when appropriate.

2. Patient Responsibilities, Representations & Health Information Disclosure
To provide safe and effective care, I agree to provide accurate and complete information about my medical history, conditions, medications, allergies, and substance use. I understand and agree that:
- Failure to disclose relevant health information may increase risks associated with treatment.
- Invigorate Health, LLC relies on the accuracy of the information I provide during telehealth consultations.
- Invigorate Health, LLC does not provide emergency care or ongoing monitoring for conditions outside the scope of the telehealth services provided.
I represent that:
- I am currently not under the influence of any illegal or controlled substances not lawfully prescribed to me.
- I am not recovering from recent illicit drug use at the time of receiving telehealth care.

3. Description of Telehealth Services
Telehealth care may include, but is not limited to:
- Review of medical history and questionnaires
- Video or audio consultations
- Remote monitoring of symptoms
- Ordering and reviewing diagnostic testing or labs
- Prescribing medications when clinically appropriate
- Providing recommendations, treatment plans, and follow-up instructions
Telehealth services are provided under the license and protocols of Dr. Mona Tomescu, and may be delivered by licensed and credentialed healthcare professionals acting within their scope of practice.

4. Risks and Limitations of Telehealth
I understand that telehealth has limitations and carries certain risks, including technology issues, incomplete information, and clinical risks. Medical risks related to treatments may include:
- Injury, bleeding, infection, inflammation/swelling
- Bruising or scarring
- IV infiltration or extravasation
- Misplacement of IV lines
- Air embolism
- Fluid overload
- Adverse or allergic reactions
- Nausea or lightheadedness
- Nerve irritation or injury
- Fainting or dizziness

5. Emergency Protocol
Invigorate Health, LLC is not an emergency medical provider. If I experience a medical emergency, I will immediately call 911 or go to the nearest emergency room.

6. Privacy Practices & Use of Protected Health Information (PHI)
Invigorate Health, LLC complies with HIPAA and other privacy laws. My PHI may be used for treatment, healthcare operations, quality improvement, referrals, and legal requirements.

7. Consent to Treatment & Medication
I authorize Invigorate Health, LLC to evaluate, diagnose, treat, and prescribe medications when appropriate.';
    }

    private function gallbladderConsentText(): string
    {
        return 'Gallbladder Disease Consent

Read the following for more information about this product and its potential side effects.

Gallbladder disease information: You noted that you have gallbladder disease or previous removal of your gallbladder. This medication may still be a good option. However, this medication can affect how the body handles fats and bile. If you have had your gallbladder removed, the body\'s ability to store and release bile is altered. Bile is crucial for digestion and fat absorption.

This medication may increase the likelihood of gastrointestinal side effects in these individuals because it can alter fat metabolism and bile flow. This can lead to symptoms such as diarrhea and stomach pain. Additionally, medications that affect digestion and appetite, like this medication, might alter the absorption and metabolism of other nutrients (like fat soluble vitamins such as vitamin A, D, E, and K) and medications. This is particularly important for those without a gallbladder, as their digestive system already operates differently from those with a functioning gallbladder.

If you wish to move forward, it is important to eat smaller and more frequent meals. In addition, to ensure that you\'re receiving enough vitamins, you should avoid processed foods while eating plenty of fruits and vegetables, as well as considering the use of a multi-vitamin unless told by your provider to avoid these for other reasons.

If you have asymptomatic gallstones, please note that these medications and weight loss itself may result in gallstone formation which could result in the obstruction of the normal flow of bile which can result in infection, pancreatitis, and/or emergent need for gallbladder removal. It is important to receive prompt medical evaluation if symptoms appear as delayed action may result in serious harm or death if untreated.';
    }

    private function thyroidConsentText(): string
    {
        return 'THYROID CONSENT

These medications can affect how your body absorbs or responds to thyroid therapy. If you have thyroid disease, your provider may need to adjust your dosage or monitor your thyroid levels more closely after starting treatment.';
    }

    private function mwlTruthfulnessConsentText(): string
    {
        return 'Consent (Truthfulness)

Read the following for more information about this product and its potential side effects:

It is not safe to take these medications while pregnant or breastfeeding. The FDA advises that these medications may pose a risk to a developing fetus. Oral contraceptives alone may not be effective, as the medication can reduce their effectiveness. The FDA specifically recommends continuing oral contraception alongside a barrier method (like condoms) for the first month after starting a weight loss medication and for the first month after any dose increase. Alternatively, you can switch to a non-oral contraceptive method (such as an IUD or implant) before beginning the medication. After stopping the medication, you should continue using a backup method, such as condoms, for two months to ensure the medication has fully cleared your system before trying to conceive. Additionally, its safety during breastfeeding is unknown, so if you are nursing, consult your doctor to explore safer weight loss options.

The traditional use of weight loss medications is for individuals with a BMI of 30 and above or to those who are overweight who have associated health conditions. Using it for someone with a BMI range (27-29) without an accompanying health condition is termed "off-label." Using a medication "off-label" refers to the practice of prescribing a drug for a purpose, age group, dosage, or form of administration that is not included in the approved labeling by regulatory agencies like the U.S. Food and Drug Administration (FDA). While a medication undergoes rigorous testing for specific uses before receiving approval, healthcare providers may discover through clinical experience or research that it can be effective for treating other conditions. There may be benefits such as weight reduction for individuals within your range. If you agree to this off-label use, it\'s crucial to follow the prescribed regimen and report any concerns. Please discuss any questions with us.

I hereby acknowledge and agree that by selecting and purchasing this product (any of the offered plans), I am entering into a binding commitment for the full duration of the selected term. I further acknowledge and agree that, in the event I terminate the program prior to the expiration of the agreed-upon term, I will be liable for and shall pay the early termination fee as specified in the Terms and Conditions, which I have read, understood, and accepted.';
    }

    private function glp1ConsentText(): string
    {
        return 'Consent (GLP-1 and GLP-1/GIP)

Indication for Use
You are requesting treatment with a GLP-1 (Ozempic, Wegovy, or compounded semaglutide) or GIP/GLP-1 receptor agonist (Mounjaro, Zepbound, or compounded tirzepatide) medication as part of your treatment plan for the management of weight or obesity. These medications work by mimicking the action of incretin hormones, which help regulate blood sugar levels, promote feeling full, and reduce food intake.

Potential Benefits
- Weight loss or weight management
- Improved blood glucose control
- Reduced cardiovascular risk
- Potential improvement in overall metabolic health

Potential Side Effects
While these medications can be beneficial, they may also cause side effects. Although not common, these medications can result in emergency room visits, hospitalizations, or even death.

Common Side Effects: Nausea, Vomiting, Diarrhea, Constipation, Decreased appetite, Indigestion.

Serious Side Effects: Pancreatitis (inflammation of the pancreas), Hypoglycemia (low blood sugar) especially when used with other diabetes medications, Gallbladder disease (e.g. gallstones), Kidney problems, Allergic reactions (e.g. rash, itching, swelling), Gastroparesis (paralysis of the bowels).

Risks and Considerations
- Pancreatitis: There is a risk of developing pancreatitis. If you experience severe abdominal pain, nausea, or vomiting, you should contact your healthcare provider immediately.
- Thyroid Tumors: Animal studies have shown an increased risk of thyroid tumors with certain GLP-1 medications. Although this has not been confirmed in humans, please inform your healthcare provider if you have a history of thyroid cancer.
- Hypoglycemia: When taken with other diabetes medications, particularly insulin or sulfonylureas, there is a risk of low blood sugar. It is important that your provider knows if any of these medications are added to your regimen.
- Kidney Function: This medication may affect kidney function, particularly in patients with existing kidney disease. Regular monitoring of kidney function may be required.

I acknowledge the potential benefits, risks, and side effects of GLP-1 or GIP/GLP-1 receptor agonist medications. I understand the importance of regular monitoring and follow-up appointments. I consent to the use of GLP-1 or GIP/GLP-1 receptor agonist medications as part of my treatment plan for overweight or obesity.';
    }

    private function aaTruthfulnessConsentText(): string
    {
        return 'Consent (Truthfulness – General Intake)

Please attest to the following confirming that all information you have provided to us is true and complete.

Consent: I verify that I am the patient and that I have answered the questions asked in this intake form. I confirm that I have reviewed and understood all the questions asked of me. I attest that the answers and information I have provided in this questionnaire is true and complete to the best of my knowledge. I understand that it is critical to my health to share complete health information with my doctor. I will not hold the doctor or affiliated medical practice responsible for any oversights or omissions, whether intentional or not, in the information that I provided.';
    }

    private function aaInformedConsentText(): string
    {
        return 'Consent (Informed Consent Statement)

Indication for Use
You are requesting treatment with one or more lifestyle-enhancement therapies including MIC+B12 injections, Vitamin B12 (cobalamin), Glutathione, NAD+ (Nicotinamide Adenine Dinucleotide), and/or Metformin to support metabolism, enhance energy production, promote cellular health, and potentially provide anti-aging benefits. These therapies are not intended for treatment of any specific medical condition.

Background Information
- MIC+B12 combines lipotropic agents (Methionine, Inositol, Choline) with Vitamin B12 to aid fat metabolism and boost energy.
- Vitamin B12 is essential for red blood cell production, neurological function, and DNA synthesis.
- Glutathione is a natural antioxidant that reduces oxidative stress, supports detoxification, and enhances immune function.
- NAD+ is vital for mitochondrial energy production, DNA repair, and cellular health.
- Metformin is prescribed for type 2 diabetes and is studied off-label for anti-aging/metabolic benefits.

Off-Label Use Notice
Glutathione, NAD+, and Metformin are not FDA-approved for wellness or anti-aging. Their use in this program is off-label.

Potential Benefits
- MIC+B12: Enhanced metabolism
- Vitamin B12: Improved energy and nerve function
- NAD+: Increased mitochondrial energy
- Glutathione: Antioxidant protection
- Metformin: Improved insulin sensitivity

Potential Risks & Side Effects
- MIC+B12: Injection site redness, nausea, allergic reaction
- Vitamin B12: Mild diarrhea, rash, rare severe allergy
- Glutathione: GI discomfort, rash, rare allergy
- NAD+: Nausea, flushing, headache, abdominal discomfort
- Metformin: GI upset, rare lactic acidosis

Responsibilities
- Follow the treatment plan
- Report symptoms
- Do not change dosage without approval
- Attend follow-ups

Patient Acknowledgment & Consent
I have read and understood the information provided in this consent form. I understand the off-label nature of some therapies.';
    }
}
