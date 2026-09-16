<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Push the client-mandated GLP-1 clinical note instruction to existing rows.
 *
 * The AiInstructionSetsSeeder skips contexts that already have an active row,
 * so it cannot update live/staging databases. This migration runs via
 * `artisan migrate --force` in the auto-deploy pipeline, ensuring both servers
 * receive the new instruction without a manual step.
 *
 * Admins can still further edit the instruction at /admin/ai/clinical_note/edit
 * after this migration runs — this migration does not lock the row.
 *
 * down() is intentionally a no-op: content rollbacks are unsafe because the
 * prior text is unknown at rollback time and admins may have edited further.
 */
return new class extends Migration
{
    private const INSTRUCTIONS =
        "Write a structured clinical note for the reviewing provider to edit and sign. Follow this exact format and always include every section below.\n"
        . "\n"
        . "SECTION 1 — PATIENT SUMMARY (bullet points)\n"
        . "Include: name, DOB, PMH, allergies, state/location, height, weight, BMI, and requested medication.\n"
        . "- If no PMH or allergies are reported, write: None reported.\n"
        . "- Do not mention needing more information.\n"
        . "- List the preferred GLP-1 treatment and duration if indicated.\n"
        . "- If BMI is within the healthy range (18.5–24.9), include: Current BMI is within the healthy range; care will focus on supporting healthy habits, metabolic improvements, and ensuring weight remains within safe parameters.\n"
        . "- BMI reference: Underweight (<18.5), Healthy Weight (18.5–24.9), Overweight (25.0–29.9), Obese Class I (30–34.9), Class II (35–39.9), Class III (40+).\n"
        . "\n"
        . "SECTION 2 — CONTRAINDICATIONS / WARNINGS\n"
        . "Search the intake for: eating disorder, diabetes, sulfonylurea use (glimepiride/Amaryl, glipizide/Glucotrol, glyburide/Diabeta/Glynase), thyroid cancer history, MEN Type 2, pancreatitis, gallbladder disease, pregnancy, or breastfeeding.\n"
        . "If none are present, write exactly: none listed on intake. Do not elaborate.\n"
        . "\n"
        . "SECTION 3 — PRESCRIBED MEDICATIONS\n"
        . "List every approved medication with its term, frequency, dose ladder, and refills. List every declined medication as declined.\n"
        . "\n"
        . "SECTION 4 — SIDE EFFECT COUNSELLING (include verbatim)\n"
        . "Patient informed of potential side effects including but not limited to: Allergic reaction, Seizure, Nausea, Vomiting, Diarrhea, Constipation, Depression, Gallstones/Gallbladder disease and that they must seek medical care immediately for evaluation should these symptoms develop.\n"
        . "\n"
        . "SECTION 5 — INTAKE ATTESTATIONS (include verbatim, as a bullet list)\n"
        . "Intake indicates:\n"
        . "- No family history of Medullary Thyroid Cancer or MEN2.\n"
        . "- There is no active hepatobiliary disease or pancreatitis.\n"
        . "- Patient advised to reach out with any questions or concerns.\n"
        . "- Patient advised to follow up with their PCP to discuss current treatment plan and discuss treatment with GLP-1 therapy for management of their metabolic health and weight loss program.\n"
        . "- Patient agrees to update their PCP with the addition of GLP-1 therapy to their medications.\n"
        . "- Per intake review, patient is currently not taking Insulin or other undisclosed Type II Diabetes medications and will update our medical team for ANY medication changes during their treatment program.\n"
        . "- Patient understands to monitor BMI and weight loss progress, and once a healthy goal BMI is reached, we can begin tapering the medication or consider transitioning to a maintenance dose.\n"
        . "- This medication is not FDA approved and its use is \"off label and compounded\" for the treatment of weight loss and metabolic syndrome.\n"
        . "\n"
        . "SECTION 6 — LIABILITY STATEMENT (include verbatim)\n"
        . "Treatment recommendations are based on a thorough review of the intake and medical history provided by the patient; the physician is not liable for adverse events that may occur due to any conditions or medications that were withheld or undisclosed during this review.\n"
        . "\n"
        . "SECTION 7 — SIGNATURE LINE\n"
        . "End with: PRESCRIBED BY:\n"
        . "(The provider will fill in their name — do not invent a name.)\n"
        . "\n"
        . "If the provider has already written text, treat it as the steer: keep their words, build around them, and do not repeat back anything they have already said.\n"
        . "Use only information present in the case record. Never invent history, measurements, or dates.";

    public function up(): void
    {
        // Update every active clinical_note instruction set (normally exactly one).
        // If no row exists yet (fresh install before the seeder runs), this is a
        // silent no-op — the seeder will create the row with the correct text.
        DB::table('ai_instruction_sets')
            ->where('context', 'clinical_note')
            ->where('is_active', true)
            ->update([
                'name'         => 'GLP-1 clinical note — standard template',
                'instructions' => self::INSTRUCTIONS,
                'tone'         => 'Clinical, structured, GLP-1 comprehensive',
                'version'      => DB::raw('version + 1'),
                'updated_by'   => null,
                'updated_at'   => now(),
            ]);
    }

    public function down(): void
    {
        // Content rollback is intentionally omitted — prior text is not stored
        // here, and admins may have edited further since the migration ran.
    }
};
