<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

/**
 * Attach questionnaires to offerings.
 *
 * Safe to re-run: syncWithoutDetaching never removes questionnaires already
 * attached by other means, and duplicate pivot rows are not created.
 *
 * Standard  → sort_order 1 — all active offerings
 * GLP       → sort_order 2 — all active offerings (questions are conditionally
 *              hidden by depends_on operators when the patient is not on GLP)
 * NAD       → sort_order 3 — NAD-category offerings only
 */
class AttachQuestionnairesToOfferingsSeeder extends Seeder
{
    public function run(): void
    {
        $standard = Questionnaire::where('name', 'Standard Questionnaire')->first();
        $glp      = Questionnaire::where('name', 'GLP Questionnaire')->first();
        $nad      = Questionnaire::where('name', 'NAD Questionnaire')->first();

        if (! $standard) {
            $this->command->error('Standard Questionnaire not found — run OfferIntakeQuestionnairesSeeder first.');
            return;
        }

        if (! $glp) {
            $this->command->error('GLP Questionnaire not found — run OfferIntakeQuestionnairesSeeder first.');
            return;
        }

        // ── Standard + GLP → all active offerings ─────────────────────────────

        $offerings = Offering::where('is_active', true)->get();

        if ($offerings->isEmpty()) {
            $this->command->warn('No active offerings found.');
            return;
        }

        $attached = 0;

        foreach ($offerings as $offering) {
            $offering->questionnaires()->syncWithoutDetaching([
                $standard->id => ['is_required' => true, 'sort_order' => 1],
                $glp->id      => ['is_required' => true, 'sort_order' => 2],
            ]);
            $attached++;
            $this->command->line("  ✓ [standard+glp] {$offering->name}");
        }

        $this->command->info("Standard + GLP attached to {$attached} offering(s).");

        // ── NAD → NAD-category offerings only ─────────────────────────────────

        if (! $nad) {
            $this->command->warn('NAD Questionnaire not found — run NadQuestionnaireSeeder first. Skipping NAD attachment.');
            return;
        }

        $nadCategory = OfferingCategory::where('name', 'NAD')->first();

        if (! $nadCategory) {
            $this->command->warn('NAD offering category not found — run NadOfferingsSeeder first. Skipping NAD attachment.');
            return;
        }

        $nadOfferings = Offering::where('category_id', $nadCategory->id)
            ->where('is_active', true)
            ->get();

        if ($nadOfferings->isEmpty()) {
            $this->command->warn('No active NAD offerings found — skipping NAD questionnaire attachment.');
            return;
        }

        $nadAttached = 0;

        foreach ($nadOfferings as $offering) {
            $offering->questionnaires()->syncWithoutDetaching([
                $nad->id => ['is_required' => true, 'sort_order' => 3],
            ]);
            $nadAttached++;
            $this->command->line("  ✓ [nad] {$offering->name}");
        }

        $this->command->info("NAD Questionnaire attached to {$nadAttached} NAD offering(s).");
    }
}
