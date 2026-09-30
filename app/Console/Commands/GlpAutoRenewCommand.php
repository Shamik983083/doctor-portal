<?php

namespace App\Console\Commands;

use App\Models\CaseEvent;
use App\Models\CaseOffering;
use App\Models\CasePrescription;
use App\Models\OfferingCategory;
use App\Models\PatientCase;
use App\Services\GlpDoseTitrationService;
use App\Services\WebhookDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Daily scheduler that finds monthly GLP injectable cases completed ~30 days
 * ago and automatically creates a renewal case with the next dose level
 * pre-prescribed. Fires a `case_auto_renewed` webhook to the tenant portal.
 *
 * Run:    php artisan glp:auto-renew
 * Dry-run: php artisan glp:auto-renew --dry-run
 * Scheduled: daily at 08:30 UTC in routes/console.php
 */
class GlpAutoRenewCommand extends Command
{
    protected $signature   = 'glp:auto-renew {--dry-run : Preview without writing anything}';
    protected $description = 'Auto-create renewal cases for monthly GLP injectable prescriptions due today';

    // Renewal window: completed_at between 28 and 32 days ago (±2 day tolerance).
    private const WINDOW_MIN_DAYS = 28;
    private const WINDOW_MAX_DAYS = 32;

    public function __construct(
        private GlpDoseTitrationService $titration,
        private WebhookDispatcher $webhookDispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written.');
        }

        $glpCategoryId = OfferingCategory::where('name', 'GLP')->value('id');

        if (!$glpCategoryId) {
            $this->error('GLP offering category not found. Run OfferingCategoriesSeeder first.');
            return self::FAILURE;
        }

        $windowStart = now()->subDays(self::WINDOW_MAX_DAYS)->startOfDay();
        $windowEnd   = now()->subDays(self::WINDOW_MIN_DAYS)->endOfDay();

        // Find completed GLP injectable 1-month cases in the renewal window.
        $cases = PatientCase::where('status', PatientCase::STATUS_COMPLETED)
            ->where('is_auto_renewal', false)
            ->whereBetween('completed_at', [$windowStart, $windowEnd])
            ->whereHas('caseOfferings', fn ($q) =>
                $q->where('month_frequency', 1)
                  ->whereHas('offering', fn ($oq) =>
                      $oq->where('category_id', $glpCategoryId)
                         ->where('formulation_type', 'injectable')
                  )
            )
            ->whereHas('casePrescriptions')
            ->with([
                'patient',
                'partner',
                'caseOfferings.offering',
                'casePrescription.medications',
                'clinician',
            ])
            ->get();

        if ($cases->isEmpty()) {
            $this->info('No cases due for renewal today.');
            return self::SUCCESS;
        }

        $this->info("Found {$cases->count()} candidate case(s) in renewal window.");

        $processed = 0;
        $skippedDuplicate = 0;
        $skippedMaxDose = 0;
        $skippedNoOffering = 0;
        $errors = 0;

        foreach ($cases as $case) {
            try {
                $result = $this->processCase($case, $dryRun);
                match ($result) {
                    'processed'        => $processed++,
                    'duplicate'        => $skippedDuplicate++,
                    'max_dose'         => $skippedMaxDose++,
                    'no_offering'      => $skippedNoOffering++,
                    default            => null,
                };
            } catch (\Throwable $e) {
                $errors++;
                Log::error('GlpAutoRenew: failed to process case', [
                    'case_id' => $case->id,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                $this->error("  ERROR case #{$case->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Renewal cases created', $processed],
                ['Skipped — already renewed', $skippedDuplicate],
                ['Skipped — max dose reached', $skippedMaxDose],
                ['Skipped — no titratable offering', $skippedNoOffering],
                ['Errors', $errors],
            ]
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processCase(PatientCase $case, bool $dryRun): string
    {
        // ── 1. Find the titratable offering in this case ──────────────────────
        $offering = null;
        foreach ($case->caseOfferings as $co) {
            $off = $co->offering;
            if ($off && $this->titration->isTitratable($off)) {
                $offering = $off;
                break;
            }
        }

        if (!$offering) {
            $this->line("  skip  #{$case->id} — no titratable offering found");
            return 'no_offering';
        }

        // ── 2. Duplicate guard — existing refill case after this case's completed_at ──
        $alreadyRenewed = PatientCase::where('patient_id', $case->patient_id)
            ->where('partner_id', $case->partner_id)
            ->where('is_refill', true)
            ->where('created_at', '>', $case->completed_at)
            ->exists();

        if ($alreadyRenewed) {
            $this->line("  skip  #{$case->id} — renewal already exists for patient #{$case->patient_id}");
            return 'duplicate';
        }

        // ── 3. Resolve next dose level ─────────────────────────────────────────
        if ($this->titration->isAtMaxDose($case->patient_id, $offering)) {
            $this->line("  skip  #{$case->id} — patient #{$case->patient_id} is at max dose ({$offering->name})");

            if (!$dryRun) {
                $this->webhookDispatcher->dispatch($case->partner_id, 'case_glp_max_dose_reached', [
                    'case_id'       => $case->uuid,
                    'patient_id'    => $case->patient?->uuid,
                    'offering'      => $offering->name,
                    'timestamp'     => now()->timestamp,
                ]);
            }

            return 'max_dose';
        }

        $nextLevel      = $this->titration->nextLevel($case->patient_id, $offering);
        $nextLevelIndex = $this->titration->nextLevelIndex($case->patient_id, $offering);
        $currentIndex   = $this->titration->currentLevelIndex($case->patient_id, $offering);

        if ($nextLevel === null) {
            $this->line("  skip  #{$case->id} — could not determine next dose level (legacy formula mismatch?)");
            return 'no_offering';
        }

        if ($dryRun) {
            $this->info("  [DRY] #{$case->id} → patient #{$case->patient_id} | {$offering->name} | level {$currentIndex} → {$nextLevelIndex} ({$nextLevel['label']})");
            return 'processed';
        }

        // ── 4. Get the prior prescription for reference ───────────────────────
        $priorPrescription = $case->casePrescription;

        // ── 5. Create auto-renewal case + prescription in a transaction ────────
        $newCase = DB::transaction(function () use ($case, $offering, $nextLevel, $nextLevelIndex, $priorPrescription) {
            // 5a. Create the renewal case
            $newCase = PatientCase::create([
                'uuid'           => (string) Str::uuid(),
                'partner_id'     => $case->partner_id,
                'sub_storefront_id' => $case->sub_storefront_id,
                'patient_id'     => $case->patient_id,
                'clinician_id'   => $case->clinician_id,
                'external_id'    => 'auto-renewal-' . $case->id . '-' . now()->format('Ymd'),
                'status'         => PatientCase::STATUS_APPROVED,
                'visit_type'     => 'asynchronous',
                'is_chargeable'  => false,
                'hold_status'    => false,
                'is_refill'      => true,
                'is_auto_renewal'=> true,
                'patient_state'  => $case->patient_state,
                'assigned_at'    => now(),
                'approved_at'    => now(),
            ]);

            // 5b. Copy case_offerings from prior case (same offering, same frequency)
            foreach ($case->caseOfferings as $co) {
                CaseOffering::create([
                    'case_id'          => $newCase->id,
                    'offering_id'      => $co->offering_id,
                    'status'           => 'pending',
                    'quantity'         => $co->quantity,
                    'price'            => $co->price,
                    'dosage'           => $co->dosage,
                    'frequency'        => $co->frequency,
                    'refills'          => $co->refills,
                    'month_frequency'  => $co->month_frequency,
                    'product_key'      => $co->product_key,
                    'bundle_group'     => $co->bundle_group,
                    'formulation'      => $co->formulation,
                ]);
            }

            // 5c. Create the auto-prescription with next dose level
            $prescription = CasePrescription::create([
                'case_id'          => $newCase->id,
                'clinician_id'     => $case->clinician_id,
                'diagnoses'        => $priorPrescription?->diagnoses ?? 'GLP-1 Weight Management',
                'medical_necessity'=> $priorPrescription?->medical_necessity,
                'prescribed_at'    => now(),
                'review_status'    => CasePrescription::REVIEW_CONFIRMED,
            ]);

            $prescription->medications()->create([
                'offering_id'      => $offering->id,
                'name'             => $offering->name,
                'compound_formula' => $nextLevel['formula'] ?? null,
                'dosing'           => [
                    'medication' => $offering->name,
                    'frequency'  => 'Weekly',
                    'term'       => '1M',
                    'months'     => [$nextLevel['label'] ?? ''],
                ],
                'level_index'      => $nextLevelIndex,
                'refills'          => $offering->refills ?? 0,
                'quantity'         => $nextLevel['quantity'] ?? $offering->quantity,
                'days_supply'      => $offering->days_supply ?? 30,
                'dispense_unit'    => $offering->dispense_unit,
                'sig'              => $nextLevel['sig'] ?? $offering->sig,
            ]);

            // 5d. Case event audit trail
            CaseEvent::create([
                'case_id'    => $newCase->id,
                'event_type' => 'auto_renewal_created',
                'actor_type' => 'system',
                'actor_id'   => null,
                'payload'    => [
                    'source_case_id' => $case->id,
                    'offering_id'    => $offering->id,
                    'level_index'    => $nextLevelIndex,
                    'level_label'    => $nextLevel['label'] ?? null,
                ],
                'notes' => "Auto-renewal from case #{$case->id} — {$nextLevel['label']}",
            ]);

            return $newCase;
        });

        // ── 6. Fire webhook ────────────────────────────────────────────────────
        $this->webhookDispatcher->dispatch($case->partner_id, 'case_auto_renewed', [
            'case_id'          => $newCase->uuid,
            'source_case_id'   => $case->uuid,
            'patient_id'       => $case->patient?->uuid,
            'status'           => PatientCase::STATUS_APPROVED,
            'is_auto_renewal'  => true,
            'offering'         => $offering->name,
            'prior_level'      => [
                'index' => $currentIndex,
                'label' => $offering->levels[$currentIndex]['label'] ?? null,
            ],
            'next_level'       => [
                'index'   => $nextLevelIndex,
                'label'   => $nextLevel['label'],
                'formula' => $nextLevel['formula'] ?? null,
            ],
            'timestamp'        => now()->timestamp,
        ]);

        $this->info("  done  #{$case->id} → new case #{$newCase->id} | {$offering->name} | → {$nextLevel['label']}");

        return 'processed';
    }
}
