<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use Illuminate\Database\Seeder;

class OfferIntakeQuestionnairesSeeder extends Seeder
{
    // US states for shipping_state select options
    private array $stateOptions = [
        'Alabama (AL)'        => 'no', 'Alaska (AK)'        => 'no', 'Arizona (AZ)'     => 'no',
        'Arkansas (AR)'       => 'no', 'California (CA)'    => 'no', 'Colorado (CO)'    => 'no',
        'Connecticut (CT)'    => 'no', 'Delaware (DE)'      => 'no', 'Florida (FL)'     => 'no',
        'Georgia (GA)'        => 'no', 'Hawaii (HI)'        => 'no', 'Idaho (ID)'       => 'no',
        'Illinois (IL)'       => 'no', 'Indiana (IN)'       => 'no', 'Iowa (IA)'        => 'no',
        'Kansas (KS)'         => 'no', 'Kentucky (KY)'      => 'no', 'Louisiana (LA)'   => 'no',
        'Maine (ME)'          => 'no', 'Maryland (MD)'      => 'no', 'Massachusetts (MA)' => 'no',
        'Michigan (MI)'       => 'no', 'Minnesota (MN)'     => 'no', 'Mississippi (MS)' => 'no',
        'Missouri (MO)'       => 'no', 'Montana (MT)'       => 'no', 'Nebraska (NE)'    => 'no',
        'Nevada (NV)'         => 'no', 'New Hampshire (NH)' => 'no', 'New Jersey (NJ)'  => 'no',
        'New Mexico (NM)'     => 'no', 'New York (NY)'      => 'no', 'North Carolina (NC)' => 'no',
        'North Dakota (ND)'   => 'no', 'Ohio (OH)'          => 'no', 'Oklahoma (OK)'    => 'no',
        'Oregon (OR)'         => 'no', 'Pennsylvania (PA)'  => 'no', 'Rhode Island (RI)' => 'no',
        'South Carolina (SC)' => 'no', 'South Dakota (SD)'  => 'no', 'Tennessee (TN)'   => 'no',
        'Texas (TX)'          => 'no', 'Utah (UT)'          => 'no', 'Vermont (VT)'     => 'no',
        'Virginia (VA)'       => 'no', 'Washington (WA)'    => 'no', 'West Virginia (WV)' => 'no',
        'Wisconsin (WI)'      => 'no', 'Wyoming (WY)'       => 'no',
    ];

    public function run(): void
    {
        $this->seedStandard();
        $this->seedGlp();
    }

    // ── Standard Questionnaire ─────────────────────────────────────────────────
    // Questions tagged 'standard' (steps 1-3) + 'consent' (step 5 → mapped to step 4)

    private function seedStandard(): void
    {
        if (Questionnaire::where('name', 'Standard Questionnaire')->exists()) {
            $this->command->info('Standard Questionnaire already seeded — skipping.');
            return;
        }

        $q = Questionnaire::create([
            'name'        => 'Standard Questionnaire',
            'description' => 'Standard intake form covering personal info (step 1), health history (step 2), provider details (step 3), and consent (step 4).',
            'mode'        => 'multi',
            'purpose'     => 'clinical',
            'is_active'   => true,
        ]);

        // Merge standard questions (steps 1–3, keep numbering) and consent (step 5 → 4)
        $definitions = array_merge(
            $this->standardQuestions(),
            $this->consentQuestions(stepNumber: 4)
        );

        $this->insertQuestions($q, $definitions);
        $this->command->info('Standard Questionnaire seeded.');
    }

    // ── GLP Questionnaire ──────────────────────────────────────────────────────
    // Questions tagged 'glp' (step 4 → mapped to step 1) + 'consent' (step 5 → step 2)

    private function seedGlp(): void
    {
        if (Questionnaire::where('name', 'GLP Questionnaire')->exists()) {
            $this->command->info('GLP Questionnaire already seeded — skipping.');
            return;
        }

        $q = Questionnaire::create([
            'name'        => 'GLP Questionnaire',
            'description' => 'GLP-1 medication specific intake covering medication history and allergies (step 1) and consent (step 2).',
            'mode'        => 'multi',
            'purpose'     => 'clinical',
            'is_active'   => true,
        ]);

        $definitions = array_merge(
            $this->glpQuestions(stepNumber: 1),
            $this->consentQuestions(stepNumber: 2)
        );

        $this->insertQuestions($q, $definitions);
        $this->command->info('GLP Questionnaire seeded.');
    }

    // ── Two-pass insert ────────────────────────────────────────────────────────
    // Pass 1: insert all questions without depends_on, build key → DB id map.
    // Pass 2: resolve single equal_to operator conditions into depends_on_*.

    private function insertQuestions(Questionnaire $questionnaire, array $definitions): void
    {
        $keyToId = [];
        $created = [];

        // Pass 1 — insert without dependency links
        foreach ($definitions as $i => $def) {
            $type    = $this->normalizeType($def['type'] ?? 'input');
            $options = isset($def['options']) ? $this->convertOptions($def['options']) : null;

            $desc = $def['description'] ?? null;
            $row = $questionnaire->questions()->create([
                'question'    => $def['title'],
                'key'         => $def['key'],
                'type'        => $type,
                // placeholder is VARCHAR(255) — only store short hints, not consent prose
                'placeholder' => ($desc && strlen($desc) <= 255) ? $desc : null,
                'is_required' => !empty($def['required']),
                'is_readonly' => false,
                'is_active'   => true,
                'options'     => $options,
                'sort_order'  => $i * 10,
                'step_number' => $def['step'] ?? 1,
            ]);

            $keyToId[$def['key']] = $row->id;
            $created[$i]          = ['id' => $row->id, 'def' => $def];
        }

        // Pass 2 — resolve single equal_to operator → depends_on_*
        foreach ($created as ['id' => $id, 'def' => $def]) {
            if (empty($def['operator'])) continue;

            $conditions = $def['operator'];

            // Only map single-condition equal_to (the schema has one depends_on set)
            if (count($conditions) === 1 && $conditions[0]['operator'] === 'equal_to') {
                $cond      = $conditions[0];
                $parentKey = $cond['question_key'];
                $parentId  = $keyToId[$parentKey] ?? null;

                if ($parentId) {
                    QuestionnaireQuestion::where('id', $id)->update([
                        'depends_on_question_id' => $parentId,
                        'depends_on_operator'    => 'equals',
                        'depends_on_value'       => $cond['value'],
                    ]);
                }
            }
            // Multi-condition operators (age+BMI gates) are intentionally left null —
            // the schema supports one depends_on set; these show unconditionally.
        }
    }

    // ── Type normalisation ─────────────────────────────────────────────────────
    // 'phone' is not a stored type — fall back to 'input'.

    private function normalizeType(string $type): string
    {
        $valid = ['hidden', 'input', 'email', 'textarea', 'date', 'select', 'multiselect',
                  'radio', 'checkbox', 'file', 'number', 'height', 'weight', 'bmi'];
        return in_array($type, $valid, true) ? $type : 'input';
    }

    // ── Options conversion ─────────────────────────────────────────────────────
    // Seeder source format: ['Label text' => 'yes'/'no']
    // 'yes' = disqualifying answer, 'no' = normal answer.

    private function convertOptions(array $options): array
    {
        $result = [];
        foreach ($options as $label => $routingValue) {
            $result[] = [
                'value'         => $label,
                'is_disqualify' => $routingValue === 'yes',
            ];
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUESTION DEFINITIONS
    // ═══════════════════════════════════════════════════════════════════════════

    // ── STANDARD questions (tags: standard, steps 1–3) ─────────────────────────

    private function standardQuestions(): array
    {
        return [
            // ── STEP 1: Personal info ──────────────────────────────────────────

            ['tag' => 'standard', 'key' => 'product_pick',    'type' => 'hidden', 'title' => 'Product pick',                                                               'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'first_name',      'type' => 'input',  'title' => 'First name',                                                                  'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'last_name',       'type' => 'input',  'title' => 'Last name',                                                                   'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'email',           'type' => 'email',  'title' => 'Email',                                                                       'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'phone',           'type' => 'input',  'title' => 'Phone',                                                                       'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'address_line1',   'type' => 'input',  'title' => 'Street address',                                                              'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'address_line2',   'type' => 'input',  'title' => 'Apartment, suite, unit (optional)',                                           'required' => false, 'step' => 1],
            ['tag' => 'standard', 'key' => 'city',            'type' => 'input',  'title' => 'City',                                                                        'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'shipping_state',  'type' => 'select', 'title' => 'What state will your medication be shipped to?', 'options' => $this->stateOptions, 'required' => true, 'step' => 1],
            ['tag' => 'standard', 'key' => 'zip',             'type' => 'number', 'title' => 'ZIP code',                                                                    'required' => true,  'step' => 1],
            [
                'tag' => 'standard', 'key' => 'gender', 'type' => 'radio',
                'title' => 'Sex assigned at birth:',
                'description' => 'Helps your clinician personalize your dosing',
                'options' => ['Female' => 'no', 'Male' => 'no'],
                'required' => true, 'step' => 1,
            ],
            ['tag' => 'standard', 'key' => 'dob',         'type' => 'date',   'title' => 'Date of Birth:',                                       'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'height',       'type' => 'height', 'title' => 'What is your height and weight?',                      'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'weight',       'type' => 'weight', 'title' => 'Weight (in lbs)',                                      'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'bmi',          'type' => 'bmi',    'title' => 'Your BMI',                                             'required' => true,  'step' => 1],
            ['tag' => 'standard', 'key' => 'bmi_rating',   'type' => 'hidden', 'title' => 'BMI Rating',                                           'required' => false, 'step' => 1],
            ['tag' => 'standard', 'key' => 'age',          'type' => 'hidden', 'title' => 'Age',                                                  'required' => false, 'step' => 1],
            [
                'tag' => 'standard', 'key' => 'female_health_status', 'type' => 'checkbox',
                'title' => 'Do any of these apply to you?',
                'options' => [
                    'Currently or possibly pregnant, or actively trying to become pregnant' => 'yes',
                    'Breastfeeding or bottle feeding with breastmilk' => 'yes',
                    'Have given birth to a child within the last 6 months' => 'yes',
                    'None of the above' => 'no',
                ],
                'operator' => [['question_key' => 'gender', 'operator' => 'equal_to', 'value' => 'Female']],
                'required' => true, 'step' => 1,
            ],
            [
                'tag' => 'standard', 'key' => 'elderly_consent', 'type' => 'checkbox',
                'title' => 'Please read and acknowledge before continuing',
                'description' => 'We would like to make sure you are fully aware of some important considerations regarding GLP-1 medications, especially for older adults. These medications, while effective for weight loss and metabolic health, can sometimes cause gastrointestinal side effects like nausea, vomiting, and diarrhea. In older patients, these symptoms can lead to dehydration and may have an impact on kidney function, particularly if you have known kidney issues. Additionally, GLP-1 medications can occasionally cause dizziness or balance problems, which could raise the risk of falls. Appetite suppression and rapid weight loss may increase the risk of frailty, weakness, or malnutrition. Muscle wasting and bone demineralization is also a concern with rapid or aggressive weight loss. This is compounded in the elderly. It\'s important that your doctor is aware you are starting this medication so they can help monitor your health during treatment.',
                'options' => ['I have read and understand the considerations above.' => 'no'],
                // Multi-condition (age >= 65 AND bmi >= 22) — stored without depends_on
                'operator' => [
                    ['question_key' => 'age', 'operator' => 'equal_to_greater_than', 'value' => '65'],
                    ['question_key' => 'bmi', 'operator' => 'equal_to_greater_than', 'value' => '22'],
                ],
                'required' => true, 'step' => 1,
            ],
            [
                'tag' => 'standard', 'key' => 'metabolic_consent', 'type' => 'checkbox',
                'title' => 'Please read and acknowledge before continuing',
                'options' => [
                    'I acknowledge that with this BMI I am using these medications for metabolic health, anti-inflammatory, and better eating habits, but not for weight loss primarily.' => 'no',
                ],
                // Multi-condition (age 18-64 AND bmi 20-22) — stored without depends_on
                'operator' => [
                    ['question_key' => 'age', 'operator' => 'equal_to_greater_than', 'value' => '18'],
                    ['question_key' => 'age', 'operator' => 'less_than', 'value' => '65'],
                    ['question_key' => 'bmi', 'operator' => 'equal_to_greater_than', 'value' => '20'],
                    ['question_key' => 'bmi', 'operator' => 'less_than', 'value' => '23'],
                ],
                'required' => true, 'step' => 1,
            ],

            // ── STEP 2: Health history ─────────────────────────────────────────

            [
                'tag' => 'standard', 'key' => 'health_conditions', 'type' => 'checkbox',
                'title' => 'Do any of these apply to you?',
                'options' => [
                    'None of these' => 'no',
                    'End-stage kidney disease (on or about to be on dialysis)' => 'yes',
                    'End-stage liver disease (cirrhosis)' => 'yes',
                    'Current suicidal thoughts and/or prior suicidal attempt' => 'yes',
                    'Cancer (active diagnosis, active treatment, or in remission or cancer-free for less than 5 continuous years - does not apply to non-melanoma skin cancer that was considered cured via simple excision)' => 'yes',
                    'History of organ transplant on anti-rejection medication' => 'yes',
                    'Severe gastrointestinal condition (gastroparesis, blockage, inflammatory bowel disease)' => 'yes',
                    'Current diagnosis of or treatment for alcohol, opioid, or substance use disorder/dependence' => 'yes',
                    'Have or had an eating disorder (like anorexia or bulimia)' => 'yes',
                ],
                'required' => true, 'step' => 2,
            ],
            [
                'tag' => 'standard', 'key' => 'health_conditions_additional', 'type' => 'checkbox',
                'title' => 'Have you experienced or been diagnosed with any of the following?',
                'options' => [
                    'None of the below' => 'no',
                    'Current symptomatic gallstones' => 'yes',
                    'Diabetic Retinopathy (diabetic eye disease), damage to the optic nerve from trauma or reduced blood flow, or blindness' => 'yes',
                    'History of glucose-6-phosphate dehydrogenase (G6PD) deficiency' => 'yes',
                    'Hypoglycemia (low blood sugar)' => 'yes',
                    'Pancreatitis or Pancreatic Cancer' => 'yes',
                    'Personal or family history of thyroid cyst/nodule, thyroid cancer, medullary thyroid carcinoma, or multiple endocrine neoplasia syndrome type 2' => 'yes',
                    'QT prolongation or other heart rhythm disorder (including Heart Arrhythmia)' => 'yes',
                    'Type 1 diabetes' => 'yes',
                    'Type 2 diabetes (not on insulin)' => 'no',
                    'Type 2 diabetes (on insulin)' => 'yes',
                ],
                'required' => true, 'step' => 2,
            ],
            [
                'tag' => 'standard', 'key' => 'more_health_conditions', 'type' => 'checkbox',
                'title' => 'Do any of these apply to you?',
                'options' => [
                    'None of these' => 'no',
                    'Active Gall Bladder Disease' => 'no',
                    'Hypertension (high blood pressure)' => 'no',
                    'Sleep apnea' => 'no',
                    'High cholesterol or triglycerides' => 'no',
                    'Severe Depression' => 'no',
                    'Liver disease, including fatty liver' => 'no',
                    'Congestive heart failure' => 'no',
                    'Urinary stress incontinence' => 'no',
                    'Polycystic ovarian syndrome (PCOS)' => 'no',
                    'Clinically proven low testosterone' => 'no',
                    'Osteoarthritis' => 'no',
                    'Acid reflux' => 'no',
                    'Asthma/reactive airway disease' => 'no',
                    'Constipation' => 'no',
                    'Coronary artery disease or heart attack/stroke in last 2 years' => 'no',
                    'Hospitalization within the last 1 year' => 'no',
                    'Tumor/infection in brain/spinal cord' => 'no',
                ],
                'required' => true, 'step' => 2,
            ],

            // ── STEP 3: Provider details ───────────────────────────────────────

            [
                'tag' => 'standard', 'key' => 'taken_pain_medications_or_street_drugs', 'type' => 'select',
                'title' => 'Within the last 3 months, have you taken opiate pain medications and/or opiate-based street drugs?',
                'options' => ['Yes' => 'yes', 'No' => 'no'],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'taken_pain_medications_or_street_drugs_info', 'type' => 'textarea',
                'title' => 'Please tell us more.',
                'operator' => [['question_key' => 'taken_pain_medications_or_street_drugs', 'operator' => 'equal_to', 'value' => 'Yes']],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'blood_pressure', 'type' => 'select',
                'title' => 'What is your average blood pressure range?',
                'options' => [
                    '<120/80 (Normal)' => 'no',
                    '120-129/<80 (Elevated)' => 'no',
                    '130-139/80-89 (High Stage 1)' => 'no',
                    '≥140/90 (High Stage 2)' => 'no',
                ],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'resting_heart_rate', 'type' => 'select',
                'title' => 'How about your average resting heart rate?',
                'options' => [
                    '<60 beats per minute (Slow)' => 'no',
                    '60-100 beats per minute (Normal)' => 'no',
                    '101-110 beats per minute (Slightly Fast)' => 'no',
                    '>110 beats per minute (Fast)' => 'no',
                ],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'last_medical_evaluation', 'type' => 'select',
                'title' => 'When was the last time you had an in-person Medical Evaluation?',
                'options' => ['Less than a year ago' => 'no', '1 to 2 years ago' => 'no', 'More than 2 years ago' => 'no'],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'last_lab_tests', 'type' => 'select',
                'title' => 'When was the last time you had Lab Tests done?',
                'options' => ['Less than a year ago' => 'no', '1 to 2 years ago' => 'no', 'More than 2 years ago' => 'no'],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'offer_prescription_medications', 'type' => 'select',
                'title' => 'Are you currently taking any Prescription Medications?',
                'options' => [
                    'Yes - Please list the names and dosages' => 'no',
                    'No - I affirm I\'m not taking any medications' => 'no',
                ],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'offer_prescription_medications_info', 'type' => 'textarea',
                'title' => 'Please list your medications, strengths, and how often you take them.',
                'description' => 'Metformin 500mg, twice daily',
                'operator' => [['question_key' => 'offer_prescription_medications', 'operator' => 'equal_to', 'value' => 'Yes - Please list the names and dosages']],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'offer_medication_allergies', 'type' => 'select',
                'title' => 'Do you have any medication allergies?',
                'options' => [
                    'Yes - Please list your allergies and any known reactions' => 'no',
                    'No - I affirm I have no known drug allergies' => 'no',
                ],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'offer_medication_allergies_info', 'type' => 'textarea',
                'title' => 'Please list your allergies and any known reactions.',
                'description' => 'Penicillin, rash',
                'operator' => [['question_key' => 'offer_medication_allergies', 'operator' => 'equal_to', 'value' => 'Yes - Please list your allergies and any known reactions']],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'additional_doc_information', 'type' => 'select',
                'title' => 'Do you have any further information which you would like our medical team to know?',
                'options' => ['Yes' => 'no', 'No' => 'no'],
                'required' => true, 'step' => 3,
            ],
            [
                'tag' => 'standard', 'key' => 'additional_doc_information_info', 'type' => 'textarea',
                'title' => 'Please tell our medical team what they should know.',
                'operator' => [['question_key' => 'additional_doc_information', 'operator' => 'equal_to', 'value' => 'Yes']],
                'required' => true, 'step' => 3,
            ],
        ];
    }

    // ── GLP questions (tag: glp, original step 4) ──────────────────────────────

    private function glpQuestions(int $stepNumber = 1): array
    {
        return [
            [
                'tag' => 'glp', 'key' => 'glp1_allergies', 'type' => 'checkbox',
                'title' => 'Are you allergic to any of the following medications?',
                'options' => [
                    'Semaglutide' => 'yes',
                    'Tirzepatide' => 'yes',
                    'Liraglutide' => 'yes',
                    'Dulaglutide' => 'yes',
                    'I am NOT allergic to any of these medications' => 'no',
                ],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'current_glucose_medications', 'type' => 'checkbox',
                'title' => 'Please affirm you are not currently on any of the following medications',
                'options' => [
                    'Insulin' => 'yes',
                    'Glimepiride (Amaryl)' => 'yes',
                    'Glipizide (Glucotrol and Glucotrol XL)' => 'yes',
                    'Glyburide (Micronase, Glynase, and Diabeta)' => 'yes',
                    'Sitagliptin' => 'yes',
                    'Saxagliptin' => 'yes',
                    'Linagliptin' => 'yes',
                    'Alogliptin' => 'yes',
                    'I am NOT on any of these medications' => 'no',
                ],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'weight_loss_medications', 'type' => 'radio',
                'title' => 'Have you taken medication for weight loss within the past month?',
                'options' => ['Semaglutide' => 'no', 'Tirzepatide' => 'no', 'None' => 'no'],
                'required' => true, 'step' => $stepNumber,
            ],
            // Semaglutide follow-ups
            [
                'tag' => 'glp', 'key' => 'previous_semaglutide_medication_last_dose', 'type' => 'radio',
                'title' => 'What was your last dose?',
                'options' => [
                    'Semaglutide 0.25 mg' => 'no', 'Semaglutide 0.50 mg' => 'no',
                    'Semaglutide 1 mg' => 'no', 'Semaglutide 1.5 mg' => 'no',
                    'Semaglutide 2 mg' => 'no', 'Semaglutide 2.5 mg' => 'no',
                    'Semaglutide - Unknown' => 'no',
                ],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Semaglutide']],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'previous_semaglutide_medication_last_dose_date', 'type' => 'radio',
                'title' => 'When did you take that last dose?',
                'options' => ['0-7 days' => 'no', '8-14 days' => 'no', '15-30 days' => 'no', '30+ days' => 'no'],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Semaglutide']],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'previous_semaglutide_medication_experience', 'type' => 'radio',
                'title' => 'How would you like to continue?',
                'options' => [
                    'Stay on the same dose or equivalent dose' => 'no',
                    'I want to change medications' => 'no',
                    'Increase my dosage' => 'no',
                    'Decrease my dosage' => 'no',
                    'Let the provider decide what dose is best for me' => 'no',
                ],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Semaglutide']],
                'required' => true, 'step' => $stepNumber,
            ],
            // Tirzepatide follow-ups
            [
                'tag' => 'glp', 'key' => 'previous_tirzepatide_medication_last_dose', 'type' => 'radio',
                'title' => 'What was your last dose?',
                'options' => [
                    'Tirzepatide 2.5 mg' => 'no', 'Tirzepatide 5 mg' => 'no',
                    'Tirzepatide 7.5 mg' => 'no', 'Tirzepatide 10 mg' => 'no',
                    'Tirzepatide 12.5 mg' => 'no', 'Tirzepatide 15 mg' => 'no',
                    'Tirzepatide - Unknown' => 'no',
                ],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Tirzepatide']],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'previous_tirzepatide_medication_last_dose_date', 'type' => 'radio',
                'title' => 'When did you take that last dose?',
                'options' => ['0-7 days' => 'no', '8-14 days' => 'no', '15-30 days' => 'no', '30+ days' => 'no'],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Tirzepatide']],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'previous_tirzepatide_medication_experience', 'type' => 'radio',
                'title' => 'How would you like to continue?',
                'options' => [
                    'Stay on the same dose or equivalent dose' => 'no',
                    'I want to change medications' => 'no',
                    'Increase my dosage' => 'no',
                    'Decrease my dosage' => 'no',
                    'Let the provider decide what dose is best for me' => 'no',
                ],
                'operator' => [['question_key' => 'weight_loss_medications', 'operator' => 'equal_to', 'value' => 'Tirzepatide']],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'gastric_bypass_6_months', 'type' => 'select',
                'title' => 'Have you had gastric bypass surgery in the past 6 months?',
                'options' => ['Yes' => 'yes', 'No' => 'no'],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'glp', 'key' => 'glp_consent', 'type' => 'checkbox',
                'title' => 'Please read and acknowledge before continuing',
                'description' => 'You are requesting treatment with a GLP-1 receptor agonist (such as semaglutide) or a GLP-1/GIP receptor agonist (such as tirzepatide) for weight management. These medications work by mimicking hormones that regulate appetite and blood sugar. They may be compounded by a licensed 503A pharmacy. Compounded medications are not FDA-approved. Potential benefits include: reduced appetite, weight loss, improved blood sugar control. Potential risks and side effects include: nausea, vomiting, diarrhea, constipation, abdominal pain, injection site reactions, pancreatitis (rare), gallbladder problems (rare), thyroid tumors (observed in animal studies). You should not take these medications if you have a personal or family history of medullary thyroid carcinoma or Multiple Endocrine Neoplasia syndrome type 2.',
                'options' => ['I have read and understand the considerations above.' => 'no'],
                'required' => true, 'step' => $stepNumber,
            ],
        ];
    }

    // ── Consent questions (tag: consent, original step 5) ─────────────────────
    // Shared by both questionnaires. $stepNumber lets callers remap the step.

    private function consentQuestions(int $stepNumber): array
    {
        return [
            [
                'tag' => 'consent', 'key' => 'contact_agree', 'type' => 'checkbox',
                'title' => 'Please confirm before continuing',
                'options' => [
                    'I confirm that I am the patient completing this intake form and that my answers are accurate and complete to the best of my knowledge. I understand the importance of providing accurate health information for my care. I agree to the Truthfulness Consent, Telehealth Consent & Informed Treatment Consent.' => 'no',
                ],
                'required' => true, 'step' => $stepNumber,
            ],
            [
                'tag' => 'consent', 'key' => 'sms_consent', 'type' => 'checkbox',
                'title' => 'SMS Consent',
                'options' => [
                    'I consent to receive marketing text messages from AmeriLean and its medical providers at the phone number provided, including promotions, discounts, and product/service updates. Msg frequency varies. Msg & data rates may apply. Reply HELP for help, STOP to opt out. Consent is not a condition of purchase.' => 'no',
                ],
                'required' => false, 'step' => $stepNumber,
            ],
        ];
    }
}
