<?php

namespace Database\Seeders;

use App\Models\AiInstructionExample;
use App\Models\AiInstructionSet;
use Illuminate\Database\Seeder;

/**
 * Seeds one active instruction set per AI context.
 *
 * The migration (2026_07_20_210000_create_ai_instruction_sets_table.php)
 * seeded 3 of the 5 contexts on deploy, but only runs once. New contexts
 * added after that migration (case_summary, rejection_reason) landed with
 * no instruction rows, so AiInstructionSet::activeFor() returns null and
 * the service falls back to a deterministic local draft for those contexts.
 *
 * IDEMPOTENT STRATEGY
 * -------------------
 * A context is skipped if an active set already exists for it — an admin
 * may have customised those rows and we must not overwrite their work.
 * A context with ONLY deactivated sets gets a fresh active set (the admin
 * intentionally disabled everything and deserves a usable starting point
 * rather than permanent silence from the model).
 *
 * Examples follow the same rule: if the set was just created we seed its
 * examples; if the set already existed we leave its examples alone.
 *
 * Contexts covered (config/ai.php → contexts):
 *   clinical_note      — Clinical note on approval
 *   patient_message    — Direct message to a patient
 *   storefront_message — Message to a storefront partner
 *   case_summary       — AI case summary for the quick-review panel  ← new
 *   rejection_reason   — AI-assisted draft reason when declining     ← new
 *
 * updated_by is null — no specific user to attribute seed data to.
 * Depends on: nothing (no other seeders required first).
 */
class AiInstructionSetsSeeder extends Seeder
{
    /**
     * Each entry: context key → [name, instructions, tone, examples[]]
     * examples: [[situation, good_output, bad_output|null], ...]
     */
    private const SETS = [
        'clinical_note' => [
            'name'         => 'Default clinical note',
            'instructions' =>
                "Draft a concise clinical note for the reviewing provider to edit and sign.\n"
                . "Use only the information in this case's record and the decisions the provider has just made.\n"
                . "State facts, not conclusions the record does not support. Never invent history, measurements or dates.\n"
                . "If the provider has already written text, treat it as the steer: keep their words, build around them, and do not repeat back anything they have already said.\n"
                . "Name every approved medication with its term, frequency, dose ladder and refills. Name every declined medication as declined.\n"
                . "Do not sign the note, do not address the patient, and do not give the provider instructions.",
            'tone'         => 'Clinical, plain, no filler',
            'sort_order'   => 10,
            'examples'     => [
                [
                    'situation'   => 'The provider approved the primary medication and declined the add-on.',
                    'good_output' => 'Approved Semaglutide, 3 months, weekly, dosing L1 2.5 mg then L2 5 mg then L3 7.5 mg, 0 refills. Declined Zofran at this time.',
                    'bad_output'  => 'Approved the requested regimen. Add-on not approved.',
                ],
                [
                    'situation'   => 'The provider has already typed their own basics before asking for a draft.',
                    'good_output' => 'Their sentence is kept as written and leads the note, with the record composed around it and nothing they already said repeated.',
                    'bad_output'  => 'The draft replaces what they typed with a fresh composition.',
                ],
            ],
        ],

        'patient_message' => [
            'name'         => 'Default patient reply',
            'instructions' =>
                "Draft a reply to the patient for the provider to edit and send.\n"
                . "Read every message the patient has sent in the thread, not only the most recent one. The last message is often a thank-you, and answering that instead of their actual question is worse than not replying.\n"
                . "Plain language, sixth to eighth grade reading level. No clinical jargon.\n"
                . "Never promise a clinical outcome, never diagnose, and never state or imply that a decision has been made when it has not.\n"
                . "If the case is on hold, say plainly that it is not lost and what is being waited on.\n"
                . "Do not sign off with a provider's name. The provider sends it, so the sign-off is theirs.",
            'tone'         => 'Warm, direct, reassuring without over-promising',
            'sort_order'   => 20,
            'examples'     => [
                [
                    'situation'   => 'The patient says the video visit link will not open and asks to be called.',
                    'good_output' => 'Sorry the link is not opening. I can arrange for someone to call you instead and book the visit over the phone. Would later today or tomorrow morning suit you better?',
                    'bad_output'  => 'Please try clearing your browser cache and using a different device.',
                ],
            ],
        ],

        'storefront_message' => [
            'name'         => 'Default storefront reply',
            'instructions' =>
                "Draft a reply to the storefront partner for a team member to edit and send.\n"
                . "Business tone, not clinical. Share only what the partner is entitled to see: case status, what is blocking it and what is needed next.\n"
                . "Never include clinical detail, intake answers, diagnoses or medication specifics in a partner message.\n"
                . "Be specific about what the partner has to do, if anything.",
            'tone'         => 'Professional, brief, operational',
            'sort_order'   => 30,
            'examples'     => [],
        ],

        'case_summary' => [
            'name'         => 'Default case summary',
            'instructions' =>
                "Summarise the key clinical information from this case in 4–6 bullet points for the reviewing provider.\n"
                . "Cover: the regimen requested (medication, dose, term), relevant intake flags (allergies, co-morbidities, current GLP status), any disqualifying or elevated answers, and the recommended triage priority.\n"
                . "Write each bullet as a complete, scannable sentence starting with the topic. No headings, no preamble, no sign-off.\n"
                . "Use only what is in the case record. If a field is absent, omit that bullet rather than noting its absence.\n"
                . "The summary appears in the quick-review panel before the provider opens the full case — it must help them decide whether to act now, not re-describe what they can already see in the grid.",
            'tone'         => 'Factual, structured, clinical shorthand',
            'sort_order'   => 40,
            'examples'     => [
                [
                    'situation'   => 'Patient requested Tirzepatide 10 mg (6-month term), flagged allergy to sulfa drugs at intake, currently on GLP-1 therapy.',
                    'good_output' =>
                        "Requested Tirzepatide/B12, L4 10 mg, 6-month term — titration hold recommended.\n"
                        . "Currently on GLP-1 therapy: yes — continuation case, not a new start.\n"
                        . "Sulfa drug allergy flagged at intake — documented, no direct conflict with requested compound.\n"
                        . "Triage: Red — regimen elevation and active allergy warrant clinician review before approval.",
                    'bad_output'  =>
                        "This patient wants Tirzepatide. They have a sulfa allergy and are already on GLP-1. Please review carefully.",
                ],
            ],
        ],

        'rejection_reason' => [
            'name'         => 'Default rejection reason',
            'instructions' =>
                "Draft a professional, empathetic reason for declining this case for the provider to edit before sending.\n"
                . "State clearly why approval is not possible at this time. Be direct — do not bury the reason in hedging language.\n"
                . "If the reason relates to a disqualifying intake answer, name the clinical concern (e.g. 'the reported history of pancreatitis') without quoting the patient's exact words back to them.\n"
                . "If the patient can reapply after a change in circumstances — a follow-up lab, a consultation, a waiting period — say so briefly and factually.\n"
                . "Do not promise future approval, offer clinical advice, or include dosing information.\n"
                . "Do not apologise excessively. One sentence of acknowledgement is enough; the rest is the reason and, where applicable, the path forward.",
            'tone'         => 'Clear, professional, compassionate without being evasive',
            'sort_order'   => 50,
            'examples'     => [
                [
                    'situation'   => 'Case declined because the patient reported a history of medullary thyroid carcinoma, which is a contraindication.',
                    'good_output' =>
                        "Thank you for submitting your request. Based on the medical history provided — specifically the reported history of medullary thyroid carcinoma — we are unable to approve this regimen at this time, as GLP-1 receptor agonists are contraindicated in this setting.\n"
                        . "We recommend discussing alternative weight-management options with your primary care provider.",
                    'bad_output'  =>
                        "Unfortunately we cannot approve your request at this time. Please consult your doctor.",
                ],
                [
                    'situation'   => 'Case declined because the patient is under 18 (minor flagged by intake disqualifier).',
                    'good_output' =>
                        "We are unable to proceed with this prescription request because the information provided indicates you are under 18. Our clinical programme is available to adults only.\n"
                        . "Please reach out to a healthcare provider who specialises in paediatric care.",
                    'bad_output'  =>
                        "Your case has been declined. Thank you for your understanding.",
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::SETS as $context => $cfg) {
            // Skip if an active set already exists — admin may have customised it.
            $existing = AiInstructionSet::where('context', $context)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                $skipped++;
                $this->command->line("  skipped  [{$context}] active set already exists (id {$existing->id})");
                continue;
            }

            $set = AiInstructionSet::create([
                'context'      => $context,
                'name'         => $cfg['name'],
                'instructions' => $cfg['instructions'],
                'tone'         => $cfg['tone'],
                'version'      => 1,
                'is_active'    => true,
                'sort_order'   => $cfg['sort_order'],
                'updated_by'   => null,
            ]);

            foreach ($cfg['examples'] as $i => $ex) {
                AiInstructionExample::create([
                    'ai_instruction_set_id' => $set->id,
                    'situation'             => $ex['situation'],
                    'good_output'           => $ex['good_output'],
                    'bad_output'            => $ex['bad_output'] ?? null,
                    'sort_order'            => ($i + 1) * 10,
                ]);
            }

            $created++;
            $this->command->info("  created  [{$context}] \"{$cfg['name']}\" with " . count($cfg['examples']) . ' example(s)');
        }

        $this->command->info("AiInstructionSetsSeeder: {$created} created, {$skipped} skipped (already active).");
    }
}
