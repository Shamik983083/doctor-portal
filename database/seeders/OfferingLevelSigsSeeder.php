<?php

namespace Database\Seeders;

use App\Models\Offering;
use Illuminate\Database\Seeder;

/**
 * Backfills SIG instructions into every level of each offering's `levels` JSON.
 *
 * The prescribe form reads `lvl.sig` from each level to auto-fill the SIG field
 * when a clinician selects a dosage level. Without this, SIGs must be typed
 * manually on every prescription.
 *
 * IDEMPOTENT — only updates a level when its `sig` key is missing or has
 * changed. Re-running is safe on any environment.
 *
 * Depends on: GlpOfferingsSeeder, NadOfferingsSeeder, PeptideOfferingsSeeder
 * (offerings must exist first).
 */
class OfferingLevelSigsSeeder extends Seeder
{
    // Offering name → SIG applied to every level of that offering.
    // All levels within a product share the same SIG instruction.
    private const SIGS = [
        'Semaglutide/Cyanocobalamin (B12)'         => 'Inject 50 units subcutaneously once per week',
        'Semaglutide/Pyridoxine (B6)'              => 'Inject 50 units subcutaneously once per week',
        'Tirzepatide/Cyanocobalamin (B12)'         => 'Inject 50 units subcutaneously once per week',
        'Tirzepatide/Pyridoxine (B6)'              => 'Inject 50 units subcutaneously once per week',
        'Semaglutide/Tirzepatide'                  => 'Inject 50 units subcutaneously once per week',
        'Semaglutide Tablet (SNAC)'                => 'Take one capsule by mouth daily',
        'NAD+ (Nicotinamide Adenine Dinucleotide)' => 'Inject 25 units subcutaneously 5 days per week',
        'NAD+/Glutathione'                         => 'Inject 25 units subcutaneously 5 days per week',
        'BPC-157'                                  => 'Inject 25 units subcutaneously 5 days per week',
        'BPC-157/TB-500'                           => 'Inject 25 units subcutaneously 5 days per week',
        'Tesamorelin'                              => 'Inject 25 units subcutaneously 5 days per week',
    ];

    public function run(): void
    {
        $updated = 0;
        $skipped = 0;

        foreach (self::SIGS as $offeringName => $sig) {
            // Match all partner copies of this offering (one row per partner_id).
            Offering::withTrashed()
                ->where('name', $offeringName)
                ->each(function (Offering $offering) use ($sig, &$updated, &$skipped) {
                    $levels = $offering->levels ?? [];

                    if (empty($levels)) {
                        $skipped++;
                        return;
                    }

                    $changed = false;
                    $levels  = array_map(function (array $level) use ($sig, &$changed) {
                        if (($level['sig'] ?? null) !== $sig) {
                            $level['sig'] = $sig;
                            $changed      = true;
                        }
                        return $level;
                    }, $levels);

                    if (! $changed) {
                        $skipped++;
                        return;
                    }

                    $offering->levels = $levels;
                    $offering->saveQuietly();

                    $updated++;
                    $this->command->info("  updated  [{$offering->name}] partner #{$offering->partner_id} — {$sig}");
                });
        }

        $this->command->info("OfferingLevelSigsSeeder: {$updated} updated, {$skipped} already up-to-date or empty.");
    }
}
