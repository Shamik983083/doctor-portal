<?php

namespace App\Services;

use App\Models\PatientCase;

/**
 * Slice B — Triage classifier.
 *
 * Deterministic, config-driven (see config/triage.php). Given a case it
 * returns the highest-severity band any rule fires, plus the machine-readable
 * reason codes that got it there. It is a REVIEW-PRIORITY signal only — it
 * never approves, prescribes, holds, or cancels anything.
 *
 * Ordering guarantee: RED dominates YELLOW dominates GREEN. A case is only
 * Green when no Yellow/Red rule fired.
 */
class TriageClassifier
{
    public const GREEN  = 'green';
    public const YELLOW = 'yellow';
    public const RED    = 'red';

    private const RANK = [self::GREEN => 0, self::YELLOW => 1, self::RED => 2];

    /**
     * Classify a case. Returns:
     *   ['level' => 'green|yellow|red', 'reasons' => ['CODE: human text', ...], 'ruleset' => 'triage-v1']
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

        // ── BMI ─────────────────────────────────────────────────────────
        $bmi = $patient?->bmi;
        if ($bmi !== null) {
            if ($bmi >= $cfg['bmi']['red_at_or_above']) {
                $bump(self::RED, "BMI_CRITICAL: BMI {$bmi} at/above " . $cfg['bmi']['red_at_or_above']);
            } elseif ($bmi >= $cfg['bmi']['yellow_at_or_above']) {
                $bump(self::YELLOW, "BMI_HIGH: BMI {$bmi} at/above " . $cfg['bmi']['yellow_at_or_above']);
            } elseif ($bmi <= $cfg['bmi']['yellow_at_or_below']) {
                $bump(self::YELLOW, "BMI_LOW: BMI {$bmi} at/below " . $cfg['bmi']['yellow_at_or_below']);
            }
        }

        // ── Age ─────────────────────────────────────────────────────────
        $age = $patient?->age ?? $patient?->date_of_birth?->age;
        if ($age !== null) {
            if ($age < $cfg['age']['red_below']) {
                $bump(self::RED, "MINOR: patient age {$age} below " . $cfg['age']['red_below']);
            } elseif ($age >= $cfg['age']['yellow_at_or_above']) {
                $bump(self::YELLOW, "GERIATRIC: patient age {$age} at/above " . $cfg['age']['yellow_at_or_above']);
            }
        }

        // ── Identity verification ───────────────────────────────────────
        $idStatus = strtolower((string) $patient?->id_verified_status);
        if ($idStatus !== '' && ! in_array($idStatus, $cfg['id_verification']['cleared'], true)) {
            if (in_array($idStatus, $cfg['id_verification']['failed_values'], true)) {
                $bump($cfg['id_verification']['failed_to'], "ID_FAILED: identity verification returned '{$idStatus}'");
            } else {
                $bump($cfg['id_verification']['unverified_to'], "ID_UNVERIFIED: identity status '{$idStatus}'");
            }
        }

        // ── Workflow hold ───────────────────────────────────────────────
        if ($case->hold_status) {
            $bump($cfg['hold_is_at_least'], 'ON_HOLD: case carries a workflow hold');
        }

        // ── Elevated offerings (compounded GLP-1s, etc.) ────────────────
        $offeringNames = $case->relationLoaded('caseOfferings')
            ? $case->caseOfferings->map(fn ($co) => strtolower((string) $co->offering?->name))->all()
            : $case->offerings->map(fn ($o) => strtolower((string) $o->name))->all();
        foreach ($cfg['elevated_offerings'] as $needle) {
            foreach ($offeringNames as $name) {
                if ($name !== '' && str_contains($name, $needle)) {
                    $bump(self::YELLOW, "ELEVATED_RX: offering matches '{$needle}'");
                    break 2;
                }
            }
        }

        // ── Free-text red-flag scan (questionnaire answers + notes) ─────
        $haystack = strtolower($this->collectText($case));
        foreach (['red' => self::RED, 'yellow' => self::YELLOW] as $band => $to) {
            foreach ($cfg['red_flag_keywords'][$band] as $needle) {
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    $bump($to, strtoupper($band) . "_FLAG: intake mentions '{$needle}'");
                }
            }
        }

        return [
            'level'   => $level,
            'reasons' => array_values(array_unique($reasons)),
            'ruleset' => $cfg['version'],
        ];
    }

    /**
     * Classify and persist onto the case (does not touch clinical timestamps
     * or workflow status). Safe to call repeatedly.
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

    /**
     * Gather the free text the red-flag scan runs over, defensively handling
     * whichever relations happen to be loaded.
     */
    private function collectText(PatientCase $case): string
    {
        $parts = [];

        if ($case->relationLoaded('questionnaireResponses')) {
            foreach ($case->questionnaireResponses as $resp) {
                foreach ($resp->answers ?? [] as $answer) {
                    $parts[] = (string) ($answer->answer ?? $answer->value ?? '');
                    $parts[] = (string) ($answer->question_text ?? '');
                }
            }
        }

        if ($case->relationLoaded('caseQuestions')) {
            foreach ($case->caseQuestions as $q) {
                $parts[] = (string) ($q->answer ?? '');
                $parts[] = (string) ($q->question ?? '');
            }
        }

        if ($case->relationLoaded('clinicalNotes')) {
            foreach ($case->clinicalNotes as $note) {
                $parts[] = (string) ($note->note ?? '');
            }
        }

        $parts[] = (string) $case->support_note;

        return implode(' ', array_filter($parts));
    }
}
