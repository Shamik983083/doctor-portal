<?php

namespace App\Services;

use App\Models\PatientCase;

/**
 * Triage classifier — questionnaire-disqualifier edition (triage-v2).
 *
 * Classification sources (in evaluation order):
 *  1. Questionnaire disqualifiers — any QuestionnaireAnswer with is_disqualified=true → RED
 *  2. Identity verification        — config/triage.php id_verification block
 *  3. Workflow hold                — case.hold_status flag
 *
 * RED dominates YELLOW dominates GREEN. All matching signals are recorded as
 * reasons regardless of the final band. The classifier never approves,
 * prescribes, or blocks a case — it only assigns a review-priority band.
 */
class TriageClassifier
{
    public const GREEN  = 'green';
    public const YELLOW = 'yellow';
    public const RED    = 'red';

    private const RANK = [self::GREEN => 0, self::YELLOW => 1, self::RED => 2];

    /**
     * Classify a case. Returns:
     *   ['level' => 'green|yellow|red', 'reasons' => [...], 'ruleset' => 'triage-v2']
     */
    public function classify(PatientCase $case): array
    {
        $cfg     = config('triage');
        $level   = self::GREEN;
        $reasons = [];

        $bump = function (string $to, string $reason) use (&$level, &$reasons) {
            if (self::RANK[$to] > self::RANK[$level]) {
                $level = $to;
            }
            $reasons[] = $reason;
        };

        $patient = $case->patient;

        // ── Questionnaire disqualifiers ──────────────────────────────────────
        // Any answer where is_disqualified=true (set at intake by the form
        // controller or partner API) maps to an immediate RED signal.
        $this->evaluateQuestionnaireDisqualifiers($case, $bump);

        // ── Identity verification (config only — multi-value set logic) ──────
        $idStatus = strtolower((string) ($patient?->id_verified_status ?? ''));
        $idLabel  = $idStatus === '' ? 'not provided' : $idStatus;
        if (! in_array($idStatus, $cfg['id_verification']['cleared'], true)) {
            if (in_array($idStatus, $cfg['id_verification']['failed_values'], true)) {
                $bump($cfg['id_verification']['failed_to'], "ID_FAILED: identity verification returned '{$idLabel}'");
            } else {
                $bump($cfg['id_verification']['unverified_to'], "ID_UNVERIFIED: identity status '{$idLabel}'");
            }
        }

        // ── Workflow hold (config only) ──────────────────────────────────────
        if ($case->hold_status) {
            $bump($cfg['hold_is_at_least'], 'ON_HOLD: case carries a workflow hold');
        }

        return [
            'level'   => $level,
            'reasons' => array_values(array_unique($reasons)),
            'ruleset' => $cfg['version'],
        ];
    }

    /**
     * Classify and persist onto the case (safe to call repeatedly).
     */
    public function apply(PatientCase $case): PatientCase
    {
        $result = $this->classify($case);

        $case->forceFill([
            'triage'         => $result['level'],
            'triage_reasons' => $result['reasons'],
            'triage_ruleset' => $result['ruleset'],
            'triaged_at'     => now(),
        ])->save();

        return $case;
    }

    // ────────────────────────────────────────────────────────────────────────

    /**
     * Scan all questionnaire responses linked to this case for disqualifying
     * answers. Each answer record carries is_disqualified=true when the patient
     * selected an option flagged is_disqualify in the question's options JSON.
     *
     * Lazy-loads the needed relationships if they were not already eager-loaded
     * by the caller (CaseStateMachine, backfill command, IDV re-triage, etc.).
     */
    private function evaluateQuestionnaireDisqualifiers(PatientCase $case, callable $bump): void
    {
        if (! $case->relationLoaded('questionnaireResponses')) {
            $case->load([
                'questionnaireResponses.questionnaire',
                'questionnaireResponses.answers.question',
            ]);
        }

        foreach ($case->questionnaireResponses as $response) {
            $qName = $response->questionnaire?->name ?? 'Questionnaire';

            foreach ($response->answers as $answer) {
                if (! $answer->is_disqualified) {
                    continue;
                }

                $qKey = $answer->question?->key ?? ('q' . $answer->question_id);
                $val  = (string) ($answer->answer ?? '');

                $bump(self::RED, "DISQ:{$qName}:{$qKey}:{$val}");
            }
        }
    }
}
