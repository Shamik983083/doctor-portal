<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Partner;
use App\Models\PartnerProductPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the standard product-key → offering mappings for every active partner.
 *
 * The partner API resolves product_key + month_frequency to an offering when a
 * case arrives. Without these rows a fresh deploy has no mappings and every API
 * case submission silently lands with no offering attached.
 *
 * IDEMPOTENT — firstOrCreate on (partner_id, product_key, month_frequency,
 * offering_id) so re-running never creates duplicate rows.
 *
 * Depends on: GlpOfferingsSeeder, NadOfferingsSeeder (offerings must exist first).
 */
class PartnerProductPlanSeeder extends Seeder
{
    // product_key => [offering name, ...]
    private const PLANS = [
        'semaglutide' => [
            'Semaglutide/Cyanocobalamin (B12)',
            'Semaglutide/Pyridoxine (B6)',
            'Semaglutide Tablet (SNAC)',
        ],
        'tirzepatide' => [
            'Tirzepatide/Cyanocobalamin (B12)',
            'Tirzepatide/Pyridoxine (B6)',
        ],
        'semaglutide_tirzepatide' => [
            'Semaglutide/Tirzepatide',
        ],
        'nad' => [
            'NAD+ (Nicotinamide Adenine Dinucleotide)',
        ],
        'nad_glutathione' => [
            'NAD+/Glutathione',
        ],
        'bpc157' => [
            'BPC-157',
        ],
        'bpc157_tb500' => [
            'BPC-157/TB-500',
        ],
        'tesamorelin' => [
            'Tesamorelin',
        ],
    ];

    private const FREQUENCIES = [1, 3, 4, 6, 12];

    public function run(): void
    {
        $partners = Partner::all();

        if ($partners->isEmpty()) {
            $this->command->warn('No partners found — run DemoDataSeeder first.');
            return;
        }

        // Pre-load offerings grouped by (partner_id, name) so same-named offerings
        // across different partners resolve to the correct per-partner ID.
        $allOfferingNames = collect(self::PLANS)->flatten()->unique()->values()->all();
        $allOfferings = Offering::whereIn('name', $allOfferingNames)
            ->get(['id', 'name', 'partner_id']);

        // $offeringsByPartner[partner_id][name] = offering_id
        // $globalOfferings[name] = offering_id  (partner_id IS NULL — shared)
        $offeringsByPartner = [];
        $globalOfferings    = [];
        foreach ($allOfferings as $o) {
            if ($o->partner_id) {
                $offeringsByPartner[$o->partner_id][$o->name] = $o->id;
            } else {
                $globalOfferings[$o->name] = $o->id;
            }
        }

        $created = 0;
        $skipped = 0;
        $now     = now()->toDateTimeString();

        foreach ($partners as $partner) {
            // Collect every offering ID this partner needs so we can bulk-upsert
            // offering_partner after the plan rows. GlpOfferingsSeeder creates
            // offerings per partner but never writes the pivot; OfferingController
            // does it for admin-created offerings but not seeded ones.
            $partnerOfferingIds = [];

            // Resolve offering: prefer partner-specific row, fall back to global.
            $partnerOfferingsForThisPartner = $offeringsByPartner[$partner->id] ?? [];

            foreach (self::PLANS as $productKey => $offeringNames) {
                foreach (self::FREQUENCIES as $freq) {
                    foreach ($offeringNames as $offeringName) {
                        $offeringId = $partnerOfferingsForThisPartner[$offeringName]
                            ?? $globalOfferings[$offeringName]
                            ?? null;
                        if (! $offeringId) {
                            continue;
                        }

                        $new = PartnerProductPlan::firstOrCreate([
                            'partner_id'      => $partner->id,
                            'product_key'     => $productKey,
                            'month_frequency' => $freq,
                            'offering_id'     => $offeringId,
                        ]);

                        $new->wasRecentlyCreated ? $created++ : $skipped++;
                        $partnerOfferingIds[] = $offeringId;
                    }
                }
            }

            // Ensure offering_partner pivot rows exist with is_active = true.
            // Upsert is idempotent — existing rows are left intact (only
            // is_active and updated_at are touched if the row already exists).
            foreach (array_unique($partnerOfferingIds) as $offeringId) {
                DB::table('offering_partner')->upsert(
                    [['offering_id' => $offeringId, 'partner_id' => $partner->id, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]],
                    ['offering_id', 'partner_id'],
                    ['is_active', 'updated_at'],
                );
            }
        }

        $this->command->info("PartnerProductPlanSeeder: {$created} created, {$skipped} already existed.");
    }
}
