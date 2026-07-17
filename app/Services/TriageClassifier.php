<?php

namespace App\Services;

use App\Models\PatientCase;
use App\Models\TriageRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Slice B — Triage classifier.
 *
 * Rules are loaded from the `triage_rules` DB table (managed via the admin
 * Triage Rule Set page). Falls back to config/triage.php static thresholds
 * when the table is empty or unavailable (graceful pre-migration degradation).
 *
 * Identity-verification and workflow-hold rules remain config-only because
 * they involve multi-value set logic that doesn't fit a simple threshold row.
 *
 * Ordering guarantee: RED dominates YELLOW dominates GREEN.
 */
class TriageClassifier
{
    public const GREEN  = 'green';
    public const YELLOW = 'yellow';
    public const RED    = 'red';

    private const RANK = [self::GREEN => 0, self::YELLOW => 1, self::RED => 2];

    /**
     * Classify a case. Returns:
     *   ['level' => 'green|yellow|red', 'reasons' => [...], 'ruleset' => 'triage-v1']
     */
    public function classify(PatientCase $case): array
    {
        $cfg     = config('triage');
        $level   = self::GREEN;
        $reasons = [];
        $db      = $this->loadDbRules();

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
            $bmiRules = $db->where('type', 'bmi_threshold');
            if ($bmiRules->isNotEmpty()) {
                foreach ($bmiRules as $rule) {
                    if ($this->evalNumeric((float) $bmi, $rule->operator, (float) $rule->value)) {
                        $bump($rule->triage_result, "{$rule->label}: BMI {$bmi}");
                    }
                }
            } else {
                // config fallback
                if ($bmi >= $cfg['bmi']['red_at_or_above']) {
                    $bump(self::RED, "BMI_CRITICAL: BMI {$bmi} at/above " . $cfg['bmi']['red_at_or_above']);
                } elseif ($bmi >= $cfg['bmi']['yellow_at_or_above']) {
                    $bump(self::YELLOW, "BMI_HIGH: BMI {$bmi} at/above " . $cfg['bmi']['yellow_at_or_above']);
                } elseif ($bmi <= $cfg['bmi']['yellow_at_or_below']) {
                    $bump(self::YELLOW, "BMI_LOW: BMI {$bmi} at/below " . $cfg['bmi']['yellow_at_or_below']);
                }
            }
        }

        // ── Age ─────────────────────────────────────────────────────────
        $age = $patient?->age ?? $patient?->date_of_birth?->age;
        if ($age !== null) {
            $ageRules = $db->where('type', 'age_threshold');
            if ($ageRules->isNotEmpty()) {
                foreach ($ageRules as $rule) {
                    if ($this->evalNumeric((float) $age, $rule->operator, (float) $rule->value)) {
                        $bump($rule->triage_result, "{$rule->label}: age {$age}");
                    }
                }
            } else {
                // config fallback
                if ($age < $cfg['age']['red_below']) {
                    $bump(self::RED, "MINOR: patient age {$age} below " . $cfg['age']['red_below']);
                } elseif ($age >= $cfg['age']['yellow_at_or_above']) {
                    $bump(self::YELLOW, "GERIATRIC: patient age {$age} at/above " . $cfg['age']['yellow_at_or_above']);
                }
            }
        }

        // ── Identity verification (config only — multi-value set logic) ──
        // null/empty = status never provided → treated as unverified (Yellow).
        $idStatus  = strtolower((string) ($patient?->id_verified_status ?? ''));
        $idLabel   = $idStatus === '' ? 'not provided' : $idStatus;
        if (! in_array($idStatus, $cfg['id_verification']['cleared'], true)) {
            if (in_array($idStatus, $cfg['id_verification']['failed_values'], true)) {
                $bump($cfg['id_verification']['failed_to'], "ID_FAILED: identity verification returned '{$idLabel}'");
            } else {
                $bump($cfg['id_verification']['unverified_to'], "ID_UNVERIFIED: identity status '{$idLabel}'");
            }
        }

        // ── Workflow hold (config only) ──────────────────────────────────
        if ($case->hold_status) {
            $bump($cfg['hold_is_at_least'], 'ON_HOLD: case carries a workflow hold');
        }

        // ── Elevated offerings ───────────────────────────────────────────
        $offeringNames = $case->relationLoaded('caseOfferings')
            ? $case->caseOfferings->map(fn ($co) => strtolower((string) $co->offering?->name))->all()
            : $case->offerings->map(fn ($o) => strtolower((string) $o->name))->all();

        $offeringRules = $db->where('type', 'offering');
        if ($offeringRules->isNotEmpty()) {
            foreach ($offeringRules as $rule) {
                $needle = strtolower((string) $rule->value);
                foreach ($offeringNames as $name) {
                    if ($name !== '' && str_contains($name, $needle)) {
                        $bump($rule->triage_result, "{$rule->label}: offering matches '{$rule->value}'");
                        break;
                    }
                }
            }
        } else {
            // config fallback
            foreach ($cfg['elevated_offerings'] as $needle) {
                foreach ($offeringNames as $name) {
                    if ($name !== '' && str_contains($name, $needle)) {
                        $bump(self::YELLOW, "ELEVATED_RX: offering matches '{$needle}'");
                        break 2;
                    }
                }
            }
        }

        // ── Free-text red-flag scan ──────────────────────────────────────
        $haystack     = strtolower($this->collectText($case));
        $keywordRules = $db->where('type', 'keyword');
        if ($keywordRules->isNotEmpty()) {
            foreach ($keywordRules as $rule) {
                $needle = strtolower((string) $rule->value);
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    $bump($rule->triage_result, "{$rule->label}: intake mentions '{$rule->value}'");
                }
            }
        } else {
            // config fallback
            foreach (['red' => self::RED, 'yellow' => self::YELLOW] as $band => $to) {
                foreach ($cfg['red_flag_keywords'][$band] as $needle) {
                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        $bump($to, strtoupper($band) . "_FLAG: intake mentions '{$needle}'");
                    }
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
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function evalNumeric(float $actual, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gte'  => $actual >= $threshold,
            'lte'  => $actual <= $threshold,
            'gt'   => $actual > $threshold,
            'lt'   => $actual < $threshold,
            default => false,
        };
    }

    /** Load active DB rules, cached for 5 minutes. Falls back to empty collection on error. */
    private function loadDbRules(): Collection
    {
        try {
            return Cache::remember('triage_rules_active', 300, function () {
                return TriageRule::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            });
        } catch (\Throwable) {
            return collect();
        }
    }

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
