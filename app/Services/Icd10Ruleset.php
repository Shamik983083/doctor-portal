<?php

namespace App\Services;

use App\Models\PatientCase;
use Illuminate\Support\Facades\Log;

/**
 * Auto-populate ICD-10 diagnosis codes for a GLP-1 / weight-management case.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SOURCE AUTHORITY
 * Codes and inclusion criteria drawn from:
 *   • AMA ICD-10-CM 2024 Official Guidelines for Coding and Reporting
 *   • AACE/ACE Obesity Algorithm (2023) — recommended primary codes
 *   • CMS LCD L33822 — Intensive Behavioral Therapy for Obesity
 *   • AAFP telehealth GLP-1 prescribing guidance (2023)
 *   • Nuvation / Sesame / Hims & Hers prescribing protocols (public)
 *   • HEDIS Obesity measure technical specifications (NCQA 2024)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * PLACEHOLDER POLICY (spec C9):
 *   Any mapping marked [PLACEHOLDER] logs a warning at rule-evaluation time.
 *   The prescribe form renders the auto-populated list as editable, so a
 *   provider always has the final say. The warning makes stale placeholders
 *   visible in logs before they reach production charts.
 *
 * BOUNDARY NOTES:
 *   • Null BMI → BMI-based rules are skipped; base lifestyle code still fires.
 *   • Provider removes ALL codes → allowed; clinical override is valid (no block).
 *   • The method is deterministic and side-effect-free — safe to call on every
 *     prescribe-form load; idempotent across multiple calls for the same case.
 */
final class Icd10Ruleset
{
    // ─── BMI thresholds ──────────────────────────────────────────────────────

    /** Minimum BMI at which obesity codes apply (AACE 2023: ≥30 for E66.09). */
    private const BMI_OBESE         = 30.0;

    /** BMI at which overweight code applies when a comorbidity is present (GLP-1 eligibility). */
    private const BMI_OVERWEIGHT    = 27.0;

    /** BMI threshold for morbid obesity (E66.01). */
    private const BMI_MORBID        = 40.0;

    // ─── Comorbidity → ICD-10 mapping table ─────────────────────────────────
    // Keys are normalised label fragments matched against intake data.
    // Multiple variants per condition handle real-world free-text variation.
    // [PLACEHOLDER] marks mappings awaiting clinician sign-off on exact code.

    private const COMORBIDITY_MAP = [
        // ── Cardiovascular ────────────────────────────────────────────────────
        'hypertension' => [
            'code'        => 'I10',
            'description' => 'Essential (primary) hypertension',
            'placeholder' => false,
        ],
        'high blood pressure' => [
            'code'        => 'I10',
            'description' => 'Essential (primary) hypertension',
            'placeholder' => false,
        ],
        'htn' => [
            'code'        => 'I10',
            'description' => 'Essential (primary) hypertension',
            'placeholder' => false,
        ],
        'heart disease' => [
            'code'        => 'I25.10',
            'description' => 'Atherosclerotic heart disease of native coronary artery without angina pectoris',
            'placeholder' => true, // [PLACEHOLDER] — clinician to confirm whether I25.10 or I25.9 per case
        ],
        'coronary artery disease' => [
            'code'        => 'I25.10',
            'description' => 'Atherosclerotic heart disease of native coronary artery without angina pectoris',
            'placeholder' => false,
        ],
        'cad' => [
            'code'        => 'I25.10',
            'description' => 'Atherosclerotic heart disease of native coronary artery without angina pectoris',
            'placeholder' => false,
        ],
        'high cholesterol' => [
            'code'        => 'E78.5',
            'description' => 'Hyperlipidemia, unspecified',
            'placeholder' => false,
        ],
        'hyperlipidemia' => [
            'code'        => 'E78.5',
            'description' => 'Hyperlipidemia, unspecified',
            'placeholder' => false,
        ],
        'dyslipidemia' => [
            'code'        => 'E78.5',
            'description' => 'Hyperlipidemia, unspecified',
            'placeholder' => false,
        ],
        'high ldl' => [
            'code'        => 'E78.00',
            'description' => 'Pure hypercholesterolemia, unspecified',
            'placeholder' => false,
        ],

        // ── Endocrine / Metabolic ─────────────────────────────────────────────
        'type 2 diabetes' => [
            'code'        => 'E11.9',
            'description' => 'Type 2 diabetes mellitus without complications',
            'placeholder' => false,
        ],
        't2dm' => [
            'code'        => 'E11.9',
            'description' => 'Type 2 diabetes mellitus without complications',
            'placeholder' => false,
        ],
        'diabetes' => [
            'code'        => 'E11.9',
            'description' => 'Type 2 diabetes mellitus without complications',
            'placeholder' => false,
        ],
        'prediabetes' => [
            'code'        => 'R73.09',
            'description' => 'Other abnormal glucose (prediabetes)',
            'placeholder' => false,
        ],
        'pre-diabetes' => [
            'code'        => 'R73.09',
            'description' => 'Other abnormal glucose (prediabetes)',
            'placeholder' => false,
        ],
        'pre diabetes' => [
            'code'        => 'R73.09',
            'description' => 'Other abnormal glucose (prediabetes)',
            'placeholder' => false,
        ],
        'impaired fasting glucose' => [
            'code'        => 'R73.01',
            'description' => 'Impaired fasting glucose',
            'placeholder' => false,
        ],
        'pcos' => [
            'code'        => 'E28.2',
            'description' => 'Polycystic ovarian syndrome',
            'placeholder' => false,
        ],
        'polycystic ovarian' => [
            'code'        => 'E28.2',
            'description' => 'Polycystic ovarian syndrome',
            'placeholder' => false,
        ],
        'polycystic ovary' => [
            'code'        => 'E28.2',
            'description' => 'Polycystic ovarian syndrome',
            'placeholder' => false,
        ],
        'hypothyroidism' => [
            'code'        => 'E03.9',
            'description' => 'Hypothyroidism, unspecified',
            'placeholder' => false,
        ],
        'thyroid' => [
            'code'        => 'E03.9',
            'description' => 'Hypothyroidism, unspecified',
            'placeholder' => true, // [PLACEHOLDER] — "thyroid" alone does not confirm hypo vs hyper
        ],
        'metabolic syndrome' => [
            'code'        => 'E88.81',
            'description' => 'Metabolic syndrome',
            'placeholder' => false,
        ],
        'insulin resistance' => [
            'code'        => 'E88.81',
            'description' => 'Metabolic syndrome',
            'placeholder' => true, // [PLACEHOLDER] — insulin resistance → metabolic syndrome per clinician
        ],

        // ── Hepatic ───────────────────────────────────────────────────────────
        'fatty liver' => [
            'code'        => 'K76.0',
            'description' => 'Fatty (change of) liver, not elsewhere classified (NAFLD)',
            'placeholder' => false,
        ],
        'nafld' => [
            'code'        => 'K76.0',
            'description' => 'Fatty (change of) liver, not elsewhere classified (NAFLD)',
            'placeholder' => false,
        ],
        'nash' => [
            'code'        => 'K75.81',
            'description' => 'Nonalcoholic steatohepatitis (NASH)',
            'placeholder' => false,
        ],
        'nonalcoholic steatohepatitis' => [
            'code'        => 'K75.81',
            'description' => 'Nonalcoholic steatohepatitis (NASH)',
            'placeholder' => false,
        ],

        // ── Musculoskeletal ───────────────────────────────────────────────────
        'arthritis' => [
            'code'        => 'M19.90',
            'description' => 'Primary osteoarthritis, unspecified site',
            'placeholder' => true, // [PLACEHOLDER] — "arthritis" alone; knee (M17.9) or hip (M16.9) more precise
        ],
        'osteoarthritis' => [
            'code'        => 'M19.90',
            'description' => 'Primary osteoarthritis, unspecified site',
            'placeholder' => false,
        ],
        'rheumatoid arthritis' => [
            'code'        => 'M06.9',
            'description' => 'Rheumatoid arthritis, unspecified',
            'placeholder' => false,
        ],
        'joint pain' => [
            'code'        => 'M25.50',
            'description' => 'Pain in unspecified joint',
            'placeholder' => false,
        ],

        // ── Respiratory ───────────────────────────────────────────────────────
        'sleep apnea' => [
            'code'        => 'G47.33',
            'description' => 'Obstructive sleep apnea (adult)',
            'placeholder' => false,
        ],
        'obstructive sleep apnea' => [
            'code'        => 'G47.33',
            'description' => 'Obstructive sleep apnea (adult)',
            'placeholder' => false,
        ],
        'osa' => [
            'code'        => 'G47.33',
            'description' => 'Obstructive sleep apnea (adult)',
            'placeholder' => false,
        ],
        'asthma' => [
            'code'        => 'J45.909',
            'description' => 'Unspecified asthma, uncomplicated',
            'placeholder' => false,
        ],

        // ── Renal ─────────────────────────────────────────────────────────────
        'kidney disease' => [
            'code'        => 'N18.9',
            'description' => 'Chronic kidney disease, unspecified',
            'placeholder' => true, // [PLACEHOLDER] — stage (N18.1–N18.6) needed for specificity
        ],
        'ckd' => [
            'code'        => 'N18.9',
            'description' => 'Chronic kidney disease, unspecified',
            'placeholder' => true,
        ],
        'chronic kidney' => [
            'code'        => 'N18.9',
            'description' => 'Chronic kidney disease, unspecified',
            'placeholder' => true,
        ],

        // ── Mental health ─────────────────────────────────────────────────────
        'depression' => [
            'code'        => 'F32.9',
            'description' => 'Major depressive disorder, single episode, unspecified',
            'placeholder' => false,
        ],
        'anxiety' => [
            'code'        => 'F41.9',
            'description' => 'Anxiety disorder, unspecified',
            'placeholder' => false,
        ],
        'binge eating' => [
            'code'        => 'F50.81',
            'description' => 'Binge eating disorder',
            'placeholder' => false,
        ],

        // ── Lifestyle / Social ────────────────────────────────────────────────
        'tobacco' => [
            'code'        => 'F17.210',
            'description' => 'Nicotine dependence, cigarettes, uncomplicated',
            'placeholder' => false,
        ],
        'smoking' => [
            'code'        => 'F17.210',
            'description' => 'Nicotine dependence, cigarettes, uncomplicated',
            'placeholder' => false,
        ],
        'smoker' => [
            'code'        => 'F17.210',
            'description' => 'Nicotine dependence, cigarettes, uncomplicated',
            'placeholder' => false,
        ],
    ];

    // ─── Intake field names that may hold comorbidity data ───────────────────
    // Listed most-specific first. The first key that exists and is non-empty wins.
    private const COMORBIDITY_KEYS = [
        'comorbidities',
        'conditions',
        'medical_conditions',
        'health_conditions',
        'existing_conditions',
        'chronic_conditions',
        'medical_history',
        'history',
    ];

    // ─── BMI Z-code lookup ───────────────────────────────────────────────────
    private const BMI_ZCODES = [
        [25.0, 25.9, 'Z68.25', 'Body mass index [BMI] 25.0-25.9, adult'],
        [26.0, 26.9, 'Z68.26', 'Body mass index [BMI] 26.0-26.9, adult'],
        [27.0, 27.9, 'Z68.27', 'Body mass index [BMI] 27.0-27.9, adult'],
        [28.0, 28.9, 'Z68.28', 'Body mass index [BMI] 28.0-28.9, adult'],
        [29.0, 29.9, 'Z68.29', 'Body mass index [BMI] 29.0-29.9, adult'],
        [30.0, 30.9, 'Z68.30', 'Body mass index [BMI] 30.0-30.9, adult'],
        [31.0, 31.9, 'Z68.31', 'Body mass index [BMI] 31.0-31.9, adult'],
        [32.0, 32.9, 'Z68.32', 'Body mass index [BMI] 32.0-32.9, adult'],
        [33.0, 33.9, 'Z68.33', 'Body mass index [BMI] 33.0-33.9, adult'],
        [34.0, 34.9, 'Z68.34', 'Body mass index [BMI] 34.0-34.9, adult'],
        [35.0, 35.9, 'Z68.35', 'Body mass index [BMI] 35.0-35.9, adult'],
        [36.0, 36.9, 'Z68.36', 'Body mass index [BMI] 36.0-36.9, adult'],
        [37.0, 37.9, 'Z68.37', 'Body mass index [BMI] 37.0-37.9, adult'],
        [38.0, 38.9, 'Z68.38', 'Body mass index [BMI] 38.0-38.9, adult'],
        [39.0, 39.9, 'Z68.39', 'Body mass index [BMI] 39.0-39.9, adult'],
        [40.0, 44.9, 'Z68.41', 'Body mass index [BMI] 40.0-44.9, adult'],
        [45.0, 49.9, 'Z68.42', 'Body mass index [BMI] 45.0-49.9, adult'],
        [50.0, 59.9, 'Z68.43', 'Body mass index [BMI] 50.0-59.9, adult'],
        [60.0, 69.9, 'Z68.44', 'Body mass index [BMI] 60.0-69.9, adult'],
        [70.0, PHP_FLOAT_MAX, 'Z68.45', 'Body mass index [BMI] 70 or greater, adult'],
    ];

    /**
     * Generate the auto-populated ICD-10 code list for a case.
     *
     * @return array<int, array{code: string, description: string, auto_populated: bool, sort_order: int}>
     */
    public static function for(PatientCase $case): array
    {
        $case->loadMissing('patient');

        $bmi        = self::resolveBmi($case);
        $intake     = $case->clinical_intake ?? [];
        $conditions = self::extractConditions($intake);

        $codes = [];
        $seen  = [];   // deduplicate by ICD code

        // ── 1. Primary obesity / weight code ─────────────────────────────────
        if ($bmi !== null) {
            if ($bmi >= self::BMI_MORBID) {
                self::push($codes, $seen, 'E66.01', 'Morbid (severe) obesity due to excess calories', true);
            } elseif ($bmi >= self::BMI_OBESE) {
                self::push($codes, $seen, 'E66.09', 'Other obesity due to excess calories', true);
            } elseif ($bmi >= self::BMI_OVERWEIGHT) {
                // GLP-1 indication at BMI 27–29.9 requires at least one weight-related comorbidity.
                self::push($codes, $seen, 'E66.3', 'Overweight', true);
            }
        }

        // ── 2. BMI Z-code (secondary / encounter code) ────────────────────────
        if ($bmi !== null) {
            foreach (self::BMI_ZCODES as [$lo, $hi, $code, $desc]) {
                if ($bmi >= $lo && $bmi <= $hi) {
                    self::push($codes, $seen, $code, $desc, true);
                    break;
                }
            }
        }

        // ── 3. Lifestyle base code ────────────────────────────────────────────
        // Z72.3 (lack of physical exercise) appears on virtually every
        // weight-management visit per AACE 2023 billing guidance.
        self::push($codes, $seen, 'Z72.3', 'Lack of physical exercise', true);

        // ── 4. Comorbidity codes from intake ──────────────────────────────────
        foreach ($conditions as $condition) {
            $normalised = strtolower(trim($condition));

            foreach (self::COMORBIDITY_MAP as $fragment => $mapping) {
                if (str_contains($normalised, $fragment)) {
                    if ($mapping['placeholder']) {
                        Log::warning('[ICD-10 PLACEHOLDER] Comorbidity matched a mapping that requires clinician sign-off.', [
                            'condition' => $condition,
                            'fragment'  => $fragment,
                            'code'      => $mapping['code'],
                            'case_id'   => $case->id,
                        ]);
                    }
                    self::push($codes, $seen, $mapping['code'], $mapping['description'], true);
                    break; // one match per condition string is enough
                }
            }
        }

        return array_values($codes);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Add a code to the list unless already seen. Assigns sort_order on insert. */
    private static function push(array &$codes, array &$seen, string $code, string $description, bool $autoPopulated): void
    {
        if (isset($seen[$code])) {
            return;
        }
        $seen[$code] = true;
        $codes[] = [
            'code'          => $code,
            'description'   => $description,
            'auto_populated' => $autoPopulated,
            'sort_order'    => count($codes),
        ];
    }

    /**
     * Resolve BMI from the patient record.
     * Falls back to a weight/height calculation if bmi is null.
     * Returns null when insufficient data is available.
     */
    private static function resolveBmi(PatientCase $case): ?float
    {
        $patient = $case->patient;

        if (! $patient) {
            return null;
        }

        if (! empty($patient->bmi) && is_numeric($patient->bmi)) {
            return (float) $patient->bmi;
        }

        // Imperial fallback: weight (lbs) and height (inches)
        if (! empty($patient->weight) && ! empty($patient->height)) {
            $weight = (float) $patient->weight;
            $height = (float) $patient->height;

            if ($weight > 0 && $height > 0) {
                // Standard US formula: (weight_lbs / height_in²) × 703
                $bmi = ($weight / ($height * $height)) * 703;
                return round($bmi, 1);
            }
        }

        return null;
    }

    /**
     * Extract a list of condition strings from the clinical_intake JSON.
     *
     * Handles three common shapes returned by questionnaire forms:
     *   1. Array of strings:  ["hypertension", "sleep apnea"]
     *   2. Comma-separated:   "hypertension, sleep apnea"
     *   3. Object/key=true:   {"hypertension": true, "sleep_apnea": true}
     *
     * @return string[]
     */
    private static function extractConditions(array $intake): array
    {
        $raw = null;

        foreach (self::COMORBIDITY_KEYS as $key) {
            if (isset($intake[$key]) && $intake[$key] !== '' && $intake[$key] !== null) {
                $raw = $intake[$key];
                break;
            }
        }

        if ($raw === null) {
            return [];
        }

        // Shape 1: already an array of strings
        if (is_array($raw)) {
            // Shape 3 variant: {"condition": true} associative
            $first = reset($raw);
            if (is_bool($first) || $first === 1 || $first === '1') {
                return array_keys(array_filter($raw, fn($v) => (bool) $v));
            }
            return array_filter(array_map('strval', $raw));
        }

        // Shape 2: comma-separated string
        if (is_string($raw)) {
            return array_values(array_filter(
                array_map('trim', explode(',', str_replace([';', '|'], ',', $raw)))
            ));
        }

        return [];
    }
}
