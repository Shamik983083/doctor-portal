<?php

/**
 * Triage ruleset — questionnaire-disqualifier edition (triage-v2).
 *
 * Disqualifier rules are now derived directly from questionnaire question
 * options (is_disqualify: true). Any case where a patient selected a
 * disqualifying option is automatically classified RED. The rule definitions
 * live in the questionnaire question bank, not here.
 *
 * This config retains only the two non-questionnaire signals:
 *  - Identity verification (multi-value set logic, not expressible as a question option)
 *  - Workflow hold (case-level flag, not a questionnaire answer)
 *
 * Bump `version` whenever the classification logic changes; it is stamped onto
 * every case so each result can be traced back to the rules that produced it.
 */
return [
    'version' => 'triage-v2',

    // Identity verification (patients.id_verified_status).
    // Anything not in `cleared` is treated as unverified (Yellow).
    // A hard `failed` status is Red.
    'id_verification' => [
        'cleared'       => ['verified'],
        'unverified_to' => 'yellow',
        'failed_values' => ['failed'],
        'failed_to'     => 'red',
    ],

    // A case already flagged onto a workflow hold is at least Yellow.
    'hold_is_at_least' => 'yellow',
];
