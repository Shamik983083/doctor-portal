<?php

namespace Database\Seeders;

use App\Models\Offering;
use Illuminate\Database\Seeder;

/**
 * Backfill formulation_type on all offerings that are missing it.
 *
 * The GLP, NAD, and Peptide offering seeders already handle their own
 * records. This seeder catches anything else (e.g. Low Dose Naltrexone,
 * admin-created offerings) using name + compound_formula keyword detection.
 *
 * Detection rules (applied in order, first match wins):
 *   injectable → name/formula contains: inject, subcutan, vial, /mL, compounded injection
 *   oral       → name/formula contains: tablet, snac, capsule, oral, /cap, naltrexone
 *
 * Safe to re-run: only touches rows where formulation_type IS NULL.
 * Run with: php artisan db:seed --class=OfferingFormulationTypeSeeder
 */
class OfferingFormulationTypeSeeder extends Seeder
{
    public function run(): void
    {
        // First, re-run the product-specific seeders so their per-partner
        // backfill logic runs before we fall through to keyword detection.
        $this->call([
            GlpOfferingsSeeder::class,
            NadOfferingsSeeder::class,
            PeptideOfferingsSeeder::class,
        ]);

        // Now handle anything still missing formulation_type.
        $missing = Offering::withTrashed()->whereNull('formulation_type')->get();

        if ($missing->isEmpty()) {
            $this->command->info('All offerings already have formulation_type set. Nothing to do.');
            return;
        }

        $this->command->info("Found {$missing->count()} offering(s) without formulation_type. Detecting...");

        $injectableKeywords = ['inject', 'subcutan', 'vial', '/ml', 'compounded injection'];
        $oralKeywords       = ['tablet', 'snac', 'capsule', 'oral', '/cap', 'naltrexone', 'troche', 'lozenge'];

        $counts = ['injectable' => 0, 'oral' => 0, 'skipped' => 0];

        foreach ($missing as $offering) {
            $haystack = strtolower(
                $offering->name . ' ' . ($offering->compound_formula ?? '') . ' ' . ($offering->internal_name ?? '')
            );

            $detected = null;

            foreach ($oralKeywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $detected = 'oral';
                    break;
                }
            }

            if (!$detected) {
                foreach ($injectableKeywords as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $detected = 'injectable';
                        break;
                    }
                }
            }

            if (!$detected) {
                $this->command->warn("  SKIPPED  [{$offering->name}] (ID {$offering->id}) — no keyword matched. Set manually in Admin → Offerings.");
                $counts['skipped']++;
                continue;
            }

            $offering->formulation_type = $detected;
            $offering->saveQuietly();
            $this->command->line("  set {$detected}  [{$offering->name}] (ID {$offering->id})");
            $counts[$detected]++;
        }

        $this->command->info(
            "Done. injectable={$counts['injectable']} oral={$counts['oral']} skipped={$counts['skipped']}"
        );
    }
}
