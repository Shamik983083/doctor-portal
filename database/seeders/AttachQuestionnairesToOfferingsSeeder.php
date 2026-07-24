<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

/**
 * Attach Standard Questionnaire + GLP Questionnaire to every active offering.
 *
 * Safe to re-run: syncWithoutDetaching never removes questionnaires already
 * attached by other means, and duplicate pivot rows are not created.
 *
 * Standard  → sort_order 1 (shown first in the intake flow)
 * GLP       → sort_order 2 (shown second; GLP-specific questions are
 *              conditionally hidden by depends_on operators when the patient
 *              is not on a GLP product, so non-GLP offerings show no extra
 *              questions in practice)
 */
class AttachQuestionnairesToOfferingsSeeder extends Seeder
{
    public function run(): void
    {
        $standard = Questionnaire::where('name', 'Standard Questionnaire')->first();
        $glp      = Questionnaire::where('name', 'GLP Questionnaire')->first();

        if (! $standard) {
            $this->command->error('Standard Questionnaire not found — run OfferIntakeQuestionnairesSeeder first.');
            return;
        }

        if (! $glp) {
            $this->command->error('GLP Questionnaire not found — run OfferIntakeQuestionnairesSeeder first.');
            return;
        }

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
            $this->command->line("  ✓ {$offering->name}");
        }

        $this->command->info("Done — both questionnaires attached to {$attached} offering(s).");
    }
}
