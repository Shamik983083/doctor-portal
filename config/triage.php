<?php

/**
 * Slice B — Triage ruleset (config-driven, deterministic).
 *
 * This is intentionally a TRANSPARENT, VERSIONED heuristic — not hidden
 * clinical logic. Every rule that can push a case to Yellow or Red lives
 * here so it can be reviewed, adjusted per medical direction, and versioned.
 * Bump `version` whenever thresholds change; the value is stamped onto each
 * case (`triage_ruleset`) so a classification can always be traced back to
 * the rules that produced it.
 *
 * The classifier (App\Services\TriageClassifier) NEVER approves, prescribes,
 * or blocks a case. It only assigns a review-priority band. A human clinician
 * still makes every clinical decision.
 */
return [
    'version' => 'triage-v1',

    // BMI bands (patients.bmi). Weight-loss programs care about these.
    'bmi' => [
        // BMI at/above this with no red flags → still fine (Green) for GLP-1 programs,
        // but very high BMI warrants a closer look (Yellow).
        'yellow_at_or_above' => 40.0,
        // Implausible/at-risk BMI → Red (data-quality or safety concern).
        'red_at_or_above'    => 50.0,
        // Implausibly low for a weight-management request → Yellow (verify intake).
        'yellow_at_or_below' => 18.5,
    ],

    // Age bands (patients.age, falls back to date_of_birth).
    'age' => [
        'yellow_at_or_above' => 65, // geriatric — closer review
        'red_below'          => 18, // minors must not auto-pass
    ],

    // Identity verification (patients.id_verified_status).
    // Anything not in `cleared` is treated as unverified.
    'id_verification' => [
        'cleared'       => ['verified'],
        'unverified_to' => 'yellow',
        'failed_values' => ['failed'],
        'failed_to'     => 'red',
    ],

    // Free-text red-flag scan over questionnaire answers + case notes.
    // Case-insensitive substring match. Keep clinical, keep reviewable.
    'red_flag_keywords' => [
        'red' => [
            'pregnan', 'breastfeed', 'chest pain', 'suicide', 'suicidal',
            'stroke', 'heart attack', 'thyroid cancer', 'medullary',
            'pancreatitis', 'anaphyla',
        ],
        'yellow' => [
            'allerg', 'seizure', 'diabet', 'kidney', 'liver', 'eating disorder',
            'gallbladder', 'insulin', 'blood thinner', 'warfarin',
        ],
    ],

    // Offering/medication names that always warrant at least a Yellow band
    // (compounded GLP-1s etc.). Substring match on the offering name.
    'elevated_offerings' => [
        'semaglutide', 'tirzepatide', 'compound',
    ],

    // A case already flagged onto a workflow hold is at least Yellow.
    'hold_is_at_least' => 'yellow',
];
