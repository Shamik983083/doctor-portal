<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use App\Models\PartnerProductPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds Low Dose Naltrexone (LDN) as a Weight Loss offering for every active partner.
 *
 * Creates:
 *   1. The "Weight Loss" offering category (if absent).
 *   2. One offering per partner — "Low Dose Naltrexone (LDN)" — with 3 titration
 *      levels (1.5 mg → 3 mg → 4.5 mg), each carrying its own formula, sig, and
 *      per-dispense quantity.
 *   3. PartnerProductPlan rows mapping product_key "low_dose_naltrexone" ×
 *      [1, 3, 6, 12] months → the partner's offering.
 *   4. offering_partner pivot rows so every partner can prescribe the offering.
 *
 * IDEMPOTENT — safe to re-run. Existing records are never duplicated or overwritten.
 *   - Offerings are matched on (partner_id, name); soft-deleted rows are also checked.
 *   - PartnerProductPlan rows use firstOrCreate on all four key columns.
 *   - offering_partner rows are upserted on (offering_id, partner_id).
 *
 * Depends on: Partners must already exist (run DemoDataSeeder first on a fresh install).
 *
 * Run on live:  php artisan db:seed --class=LdnOfferingsSeeder
 */
class LdnOfferingsSeeder extends Seeder
{
    private const OFFERING_NAME     = 'Low Dose Naltrexone (LDN)';
    private const INTERNAL_NAME     = 'LDN';
    private const PRODUCT_KEY       = 'low_dose_naltrexone';
    private const PLAN_FREQUENCIES  = [1, 3, 6, 12];

    /**
     * Dosing levels for the prescribe form.
     *
     * Each level contains:
     *   label    — shown in the clinician level-select dropdown
     *   formula  — compound formula for this strength (sent on webhook/prescription)
     *   sig      — directions for use
     *   quantity — capsules per dispense (30-day supply regardless of billing cycle)
     */
    private const LEVELS = [
        [
            'label'    => 'LVL1 - 1.5mg (Starter)',
            'formula'  => 'Naltrexone HCl 1.5mg/capsule (30ct)',
            'sig'      => 'Take one capsule by mouth at bedtime daily',
            'quantity' => 30,
        ],
        [
            'label'    => 'LVL2 - 3mg (Intermediate)',
            'formula'  => 'Naltrexone HCl 3mg/capsule (30ct)',
            'sig'      => 'Take one capsule by mouth at bedtime daily',
            'quantity' => 30,
        ],
        [
            'label'    => 'LVL3 - 4.5mg (Maintenance)',
            'formula'  => 'Naltrexone HCl 4.5mg/capsule (30ct)',
            'sig'      => 'Take one capsule by mouth at bedtime daily',
            'quantity' => 30,
        ],
    ];

    public function run(): void
    {
        // ── 1. Category ────────────────────────────────────────────────────────
        $category = OfferingCategory::firstOrCreate(
            ['name' => 'Weight Loss'],
            [
                'description' => 'Weight loss medications including Low Dose Naltrexone and related compounds.',
                'is_active'   => true,
            ]
        );

        if ($category->wasRecentlyCreated) {
            $this->command->info('  created  category [Weight Loss]');
        } else {
            $this->command->line('  skipped  category [Weight Loss] already exists');
        }

        // ── 2. Offerings — one per partner ────────────────────────────────────
        $partners = Partner::all();

        if ($partners->isEmpty()) {
            $this->command->warn('No partners found — run DemoDataSeeder first.');
            return;
        }

        $offeringDefaults = [
            'type'                    => 'compound',
            'formulation_type'        => 'oral',
            'pharmacy_type'           => 'boothwyn',
            'compound_formula'        => 'Naltrexone HCl compounded oral capsule',
            'dispense_unit'           => 'capsule',
            'quantity'                => 30,   // 30-capsule (1-month) supply per dispense
            'days_supply'             => 30,
            'refills'                 => 0,
            'days_until_dispense'     => null,
            'directions'              => 'Take one capsule by mouth at bedtime daily as directed. '
                                        . 'Do not exceed prescribed dose.',
            'is_active'               => true,
            'is_controlled_substance' => false,
            'approval_status'         => 'approved',
            'levels'                  => self::LEVELS,
        ];

        $createdOfferings  = 0;
        $skippedOfferings  = 0;
        $createdPlans      = 0;
        $skippedPlans      = 0;

        $now = now()->toDateTimeString();

        foreach ($partners as $partner) {
            // ── 2a. Find or create the offering ───────────────────────────────
            $existing = Offering::withTrashed()
                ->where('partner_id', $partner->id)
                ->where('name', self::OFFERING_NAME)
                ->first();

            if ($existing) {
                $offeringId = $existing->id;
                $skippedOfferings++;
                $this->command->line(
                    '  skipped  [' . self::OFFERING_NAME . '] already exists'
                    . ($existing->trashed() ? ' (soft-deleted)' : '')
                    . " for partner #{$partner->id}"
                );
            } else {
                $offering = Offering::create(array_merge($offeringDefaults, [
                    'uuid'          => (string) Str::uuid(),
                    'partner_id'    => $partner->id,
                    'category_id'   => $category->id,
                    'name'          => self::OFFERING_NAME,
                    'internal_name' => self::INTERNAL_NAME,
                ]));

                $offeringId = $offering->id;
                $createdOfferings++;
                $this->command->info(
                    '  created  [' . self::OFFERING_NAME . "] for partner #{$partner->id} (offering_id={$offeringId})"
                );
            }

            // ── 2b. offering_partner pivot — ensures the partner can prescribe ─
            DB::table('offering_partner')->upsert(
                [[
                    'offering_id' => $offeringId,
                    'partner_id'  => $partner->id,
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]],
                ['offering_id', 'partner_id'],
                ['is_active', 'updated_at'],
            );

            // ── 2c. PartnerProductPlan rows × 4 frequencies ──────────────────
            foreach (self::PLAN_FREQUENCIES as $freq) {
                $plan = PartnerProductPlan::firstOrCreate([
                    'partner_id'      => $partner->id,
                    'product_key'     => self::PRODUCT_KEY,
                    'month_frequency' => $freq,
                    'offering_id'     => $offeringId,
                ], [
                    'label' => self::INTERNAL_NAME . " — {$freq}-Month",
                ]);

                if ($plan->wasRecentlyCreated) {
                    $createdPlans++;
                } else {
                    $skippedPlans++;
                }
            }
        }

        $this->command->info(sprintf(
            'LdnOfferingsSeeder: %d offering(s) created, %d skipped | %d plan(s) created, %d skipped.',
            $createdOfferings, $skippedOfferings, $createdPlans, $skippedPlans
        ));
    }
}
