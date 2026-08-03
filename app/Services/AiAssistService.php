<?php

namespace App\Services;

use App\Models\AiInstructionSet;
use App\Models\Message;
use App\Models\PatientCase;
use App\Services\Ai\AiAssistManager;
use Illuminate\Support\Facades\Log;

/**
 * Produces DRAFTS for a human to edit. It never persists, never sends and never
 * signs. Every caller receives text and is responsible for putting it in front
 * of a person.
 *
 * Two things this service is careful about, both of which are the reason it
 * exists rather than the controller calling an adapter directly:
 *
 *  1. IT READS THE PATIENT'S OWN RECORD. A note that is not grounded in this
 *     case's real answers, measurements and decisions is worse than no note. The
 *     context is built from the case, its patient, its intake questions and the
 *     decisions the provider has just made, and nothing else.
 *
 *  2. IT ALWAYS PRODUCES A GROUNDED FALLBACK FIRST. The deterministic
 *     composition is built before any provider is contacted, so if the model is
 *     off, unreachable, or returns nothing usable, the provider still gets the
 *     same grounded draft instead of an error and an empty box. Turning the
 *     model on improves the words; it is not load-bearing for the flow.
 */
class AiAssistService
{
    public function __construct(private AiAssistManager $manager) {}

    /**
     * Draft the clinical note that accompanies an approval decision.
     *
     * @param  array   $decisions     Per-medication decisions as made in the UI:
     *                                [ ['name'=>, 'decision'=>'approve|deny|none',
     *                                   'term'=>, 'frequency'=>, 'months'=>[], 'refills'=>], ... ]
     * @param  ?string $providerText  Whatever the provider has already typed. It is an
     *                                INPUT, never something this method discards.
     * @return array  { text:string, source:'model'|'local', model:?string, notice:?string }
     */
    public function draftClinicalNote(PatientCase $case, array $decisions = [], ?string $providerText = null): array
    {
        $providerText = trim((string) $providerText);
        $fallback     = $this->composeClinicalNote($case, $decisions, $providerText);

        return $this->run('clinical_note', $this->clinicalNoteInput($case, $decisions, $providerText), $fallback);
    }

    /**
     * Draft a patient-facing rejection message explaining why the case was declined.
     * Uses the rejection_reason context. Always produces a grounded fallback.
     */
    public function draftRejectionMessage(PatientCase $case, string $reason): array
    {
        $patient  = $case->patient;
        $clin     = $case->queueClinical();
        $product  = $clin['product'] ?? null;
        $productText = ($product && $product !== '-') ? " for {$product}" : '';

        $fallback = "After a thorough clinical review, the prescription request{$productText} cannot be approved at this time. "
            . "The submitted information does not meet the clinical criteria required to safely prescribe the requested medication. "
            . "Please consult with a licensed healthcare provider for alternative treatment options.";

        $lines   = ['CASE CONTEXT'];
        $lines[] = 'Decline reason: ' . $reason;
        if ($patient?->age)    { $lines[] = 'Age: ' . $patient->age; }
        if ($patient?->gender) { $lines[] = 'Sex: ' . $patient->gender; }
        if ($patient?->bmi)    { $lines[] = 'BMI: ' . $patient->bmi; }
        if ($product && $product !== '-') { $lines[] = 'Requested product: ' . $product; }

        return $this->run('rejection_reason', implode("\n", $lines), $fallback);
    }

    /**
     * Draft a reply to a patient in a case thread.
     */
    public function draftPatientReply(PatientCase $case, string $steer = ''): array
    {
        $inbound = $case->messages()
            ->where('sender_type', '!=', 'clinician')
            ->orderBy('created_at')
            ->get();

        $fallback = 'Thanks for your message. I have reviewed your case and will follow up with the detail you asked for shortly.';

        return $this->run('patient_message', $this->patientReplyInput($case, $inbound, $steer), $fallback);
    }

    /**
     * Resolve instructions, call the adapter behind its gate, and normalize.
     *
     * A failure is never fatal and never silent: the provider gets the grounded
     * local composition plus a notice saying the model did not answer, so they
     * are never handed model-quality expectations for locally-composed text.
     */
    private function run(string $context, string $input, string $fallback): array
    {
        if (! array_key_exists($context, (array) config('ai.contexts', []))) {
            throw new \InvalidArgumentException("Unknown AI context [{$context}].");
        }

        $set = AiInstructionSet::activeFor($context);

        // No active instruction set means no guidance. Composing locally is the
        // correct answer; sending an unguided prompt about a patient is not.
        if (! $set) {
            return [
                'text'   => $fallback,
                'source' => 'local',
                'model'  => null,
                'notice' => 'Composed from this case\'s own record. No active AI instructions are configured for '
                    . 'this context, so nothing was sent to a model.',
            ];
        }

        if (! $this->manager->liveDraftingEnabledForContext($context)) {
            return [
                'text'   => $fallback,
                'source' => 'local',
                'model'  => null,
                'notice' => $this->manager->disabledReasonForContext($context),
            ];
        }

        try {
            $result = $this->manager->resolveForContext($context)->draft([
                'context'      => $context,
                'instructions' => $set->toPromptInstructions(),
                'input'        => $input,
                'fallback'     => $fallback,
                'prompt_id'    => config('ai.openai.prompt_id'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI assist draft failed, falling back to local composition', [
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);
            $result = ['ok' => false, 'text' => '', 'model' => null];
        }

        $text = trim((string) ($result['text'] ?? ''));

        if (empty($result['ok']) || $text === '') {
            return [
                'text'   => $fallback,
                'source' => 'local',
                'model'  => null,
                'notice' => 'The AI provider did not return a draft, so this was composed from the case record instead.',
            ];
        }

        $max = (int) config('ai.max_output_chars', 6000);
        if ($max > 0 && mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
        }

        return [
            'text'   => $text,
            'source' => 'model',
            'model'  => $result['model'] ?? null,
            'notice' => null,
        ];
    }

    /* ------------------------------------------------------------------ input */

    /**
     * The case material the model is allowed to see for a clinical note.
     *
     * Built explicitly, field by field. It is deliberately NOT a dump of the
     * case model: a whitelist is the only way to be sure that adding a column
     * later does not quietly start sending something new to a third party.
     */
    private function clinicalNoteInput(PatientCase $case, array $decisions, string $providerText): string
    {
        $patient = $case->patient;
        $lines   = [];

        $lines[] = 'PATIENT RECORD';
        $lines[] = 'Age: ' . ($patient->age ?? 'not recorded');
        $lines[] = 'Sex: ' . ($patient->gender ?? 'not recorded');
        $lines[] = 'BMI: ' . ($patient->bmi ?? 'not recorded');
        $lines[] = 'Identity verified: ' . ($patient->id_verified_status ?? 'not recorded');
        $lines[] = 'Visit type: ' . ($case->visit_type ?? 'not recorded');

        $lines[] = '';
        $lines[] = 'TRIAGE';
        $lines[] = 'Classification: ' . ($case->triage ?? 'not classified');
        $reasons = $this->triageReasons($case);
        $lines[] = 'Reasons: ' . ($reasons === [] ? 'none recorded' : implode('; ', $reasons));

        $lines[] = '';
        $lines[] = 'INTAKE ANSWERS';
        foreach ($case->caseQuestions()->orderBy('sort_order')->get() as $q) {
            $answer = trim((string) $q->answer);
            $lines[] = '- ' . trim((string) $q->question) . ' ' . ($answer === '' ? 'Not answered' : $answer);
        }

        $lines[] = '';
        $lines[] = 'DECISIONS THE PROVIDER HAS JUST MADE';
        foreach ($decisions as $d) {
            $lines[] = '- ' . $this->decisionLine($d);
        }

        if ($providerText !== '') {
            $lines[] = '';
            $lines[] = 'WHAT THE PROVIDER HAS ALREADY WRITTEN. Keep these words, build around them, '
                . 'and do not repeat anything they have already said:';
            $lines[] = $providerText;
        }

        return implode("\n", $lines);
    }

    private function patientReplyInput(PatientCase $case, $inbound, string $steer): string
    {
        $lines = ['CASE STATUS: ' . $case->status];

        if (! empty($case->hold_status)) {
            $lines[] = 'ACTIVE HOLD: ' . $case->hold_status;
        }

        $lines[] = '';
        $lines[] = 'EVERY MESSAGE THE PATIENT HAS SENT, oldest first. Answer what they actually asked, '
            . 'which is often not the most recent message:';

        foreach ($inbound as $m) {
            $lines[] = '- ' . trim((string) $m->body);
        }

        if (trim($steer) !== '') {
            $lines[] = '';
            $lines[] = 'WHAT THE PROVIDER WANTS THE REPLY TO DO: ' . trim($steer);
        }

        return implode("\n", $lines);
    }

    /* ------------------------------------------------- local composition */

    /**
     * The deterministic composition. This is the same algorithm the design
     * preview uses, kept here as the grounded fallback.
     *
     * The dedupe at the end is what makes a redraft safe: the previous draft
     * comes back in as provider text, every fact in it is already present, so
     * regenerating returns the same note instead of stacking a second copy of
     * itself, and anything the provider wrote by hand survives.
     */
    public function composeClinicalNote(PatientCase $case, array $decisions, string $providerText = ''): string
    {
        $patient = $case->patient;
        $lines   = [];

        $sex = match (strtolower((string) ($patient->gender ?? ''))) {
            'f', 'female' => 'female',
            'm', 'male'   => 'male',
            default       => 'patient',
        };

        $lines[] = trim(($patient->age ? $patient->age . ' year old ' : '') . $sex
            . ($patient->bmi ? ', BMI ' . $patient->bmi : '')
            . ($case->partner?->name ? ', presenting through ' . $case->partner->name : '')
            . ' for weight management.');

        $reasons = $this->triageReasons($case);
        $lines[] = $reasons === []
            ? 'Intake screening flagged no contraindications.'
            : 'Intake screening flagged: ' . implode(', ', array_map('strtolower', $reasons)) . '.';

        foreach ($decisions as $d) {
            if (($d['decision'] ?? null) === 'approve') {
                $lines[] = $this->decisionLine($d);
            }
        }
        foreach ($decisions as $d) {
            if (($d['decision'] ?? null) === 'deny') {
                $lines[] = 'Declined ' . ($d['name'] ?? 'medication') . ' at this time.';
            }
        }

        $lines[] = 'Patient advised to report severe or persistent side effects and to contact the clinic with any concerns.';

        $own = trim($providerText);
        $fresh = array_values(array_filter($lines, fn ($l) => $own === '' || ! str_contains($own, $l)));

        if ($own === '') {
            return implode(' ', $fresh);
        }

        return implode(' ', array_merge([preg_replace('/([^.!?])$/u', '$1.', $own)], $fresh));
    }

    private function decisionLine(array $d): string
    {
        $decision = $d['decision'] ?? 'none';
        $name     = $d['name'] ?? 'medication';

        if ($decision === 'deny')  return 'Declined ' . $name . ' at this time.';
        if ($decision !== 'approve') return $name . ' left undecided.';

        $months = array_values(array_filter($d['months'] ?? []));
        $dosing = count($months) > 1
            ? ', dosing ' . implode(' then ', $months)
            : (isset($months[0]) ? ', ' . $months[0] : '');

        return 'Approved ' . $name
            . (isset($d['term']) ? ', ' . $d['term'] : '')
            . (isset($d['frequency']) ? ', ' . strtolower((string) $d['frequency']) : '')
            . $dosing
            . (isset($d['refills']) ? ', ' . $d['refills'] . ' refills' : '')
            . '.';
    }

    /**
     * triage_reasons is stored as JSON on the case and has been seen both as a
     * list of strings and as a list of {label} rows. Handling both is cheaper
     * than a migration and stops a shape change from emptying the note silently.
     */
    private function triageReasons(PatientCase $case): array
    {
        $raw = $case->triage_reasons;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array_filter([$raw]);
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $r) {
            if (is_string($r)) {
                $out[] = $r;
            } elseif (is_array($r)) {
                $out[] = (string) ($r['label'] ?? $r['reason'] ?? $r['rule'] ?? '');
            }
        }

        return array_values(array_filter(array_map('trim', $out)));
    }
}
