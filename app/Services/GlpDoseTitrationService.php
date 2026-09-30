<?php

namespace App\Services;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\PatientCase;
use Illuminate\Support\Facades\Log;

class GlpDoseTitrationService
{
    private ?int $glpCategoryId = null;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * True when this offering participates in the titration ladder:
     * GLP category + injectable formulation + at least 2 defined levels.
     */
    public function isTitratable(Offering $offering): bool
    {
        if ($offering->formulation_type !== 'injectable') {
            return false;
        }

        if (!is_array($offering->levels) || count($offering->levels) < 2) {
            return false;
        }

        return $offering->category_id === $this->glpCategoryId();
    }

    /**
     * The level index the patient is currently on for this offering,
     * derived from their most recent completed prescription.
     *
     * Returns:
     *   int   → the stored or matched index (0-based)
     *   null  → no prior prescription exists (treat as "not yet started")
     *   -1    → prior prescription exists but used a custom formula outside the ladder
     */
    public function currentLevelIndex(int $patientId, Offering $offering): ?int
    {
        $medication = $this->lastPrescribedMedication($patientId, $offering);

        if ($medication === null) {
            return null;
        }

        // Stored explicitly at prescription time (post-migration).
        if ($medication->level_index !== null) {
            return $medication->level_index;
        }

        // Legacy fallback: match compound_formula string against levels array.
        return $this->matchFormulaToIndex($medication->compound_formula, $offering->levels);
    }

    /**
     * The next level the patient should receive, or null when:
     *   - offering is not titratable
     *   - patient is already on the last (max) level
     *   - formula can't be matched (legacy + no level_index stored)
     *
     * Returns array with keys: label, formula, sig, quantity (same shape as offering.levels entries)
     */
    public function nextLevel(int $patientId, Offering $offering): ?array
    {
        if (!$this->isTitratable($offering)) {
            return null;
        }

        $levels = $offering->levels;
        $currentIndex = $this->currentLevelIndex($patientId, $offering);

        // No prior prescription → start at level 0 (first dose).
        if ($currentIndex === null) {
            return $levels[0] ?? null;
        }

        // Custom formula outside ladder → cannot titrate safely.
        if ($currentIndex === -1) {
            Log::warning('GlpDoseTitration: prior prescription formula did not match any level', [
                'patient_id'  => $patientId,
                'offering_id' => $offering->id,
            ]);
            return null;
        }

        $nextIndex = $currentIndex + 1;

        // Already at max dose.
        if ($nextIndex >= count($levels)) {
            return null;
        }

        return $levels[$nextIndex];
    }

    /**
     * True when the patient is on the last level of the ladder.
     */
    public function isAtMaxDose(int $patientId, Offering $offering): bool
    {
        if (!$this->isTitratable($offering)) {
            return false;
        }

        $currentIndex = $this->currentLevelIndex($patientId, $offering);

        if ($currentIndex === null || $currentIndex === -1) {
            return false;
        }

        return $currentIndex >= count($offering->levels) - 1;
    }

    /**
     * The numeric index the next level sits at (for storing on the medication row).
     * Returns null when there is no next level.
     */
    public function nextLevelIndex(int $patientId, Offering $offering): ?int
    {
        if (!$this->isTitratable($offering)) {
            return null;
        }

        $currentIndex = $this->currentLevelIndex($patientId, $offering);

        if ($currentIndex === null) {
            return 0;
        }

        if ($currentIndex === -1) {
            return null;
        }

        $nextIndex = $currentIndex + 1;

        return $nextIndex < count($offering->levels) ? $nextIndex : null;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns the CasePrescriptionMedication from the patient's most recent
     * completed case that contains this offering.
     */
    private function lastPrescribedMedication(int $patientId, Offering $offering): ?\App\Models\CasePrescriptionMedication
    {
        $case = PatientCase::where('patient_id', $patientId)
            ->where('status', PatientCase::STATUS_COMPLETED)
            ->whereHas('casePrescriptions.medications', fn ($q) =>
                $q->where('offering_id', $offering->id)
            )
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if (!$case) {
            return null;
        }

        return $case->casePrescriptions()
            ->with('medications')
            ->latest('prescribed_at')
            ->first()
            ?->medications
            ->where('offering_id', $offering->id)
            ->first();
    }

    /**
     * Match a compound_formula string against the levels array.
     * Returns the index if found, -1 if the formula exists but matches nothing,
     * null if formula is blank (treat as no prior data).
     */
    private function matchFormulaToIndex(?string $formula, array $levels): ?int
    {
        if (blank($formula)) {
            return null;
        }

        $needle = strtolower(trim($formula));

        foreach ($levels as $idx => $level) {
            $haystack = strtolower(trim($level['formula'] ?? ''));
            if ($haystack !== '' && $haystack === $needle) {
                return $idx;
            }
        }

        return -1;
    }

    /**
     * Resolve and cache the GLP offering category ID.
     */
    private function glpCategoryId(): int
    {
        if ($this->glpCategoryId === null) {
            $this->glpCategoryId = OfferingCategory::where('name', 'GLP')->value('id') ?? 0;
        }

        return $this->glpCategoryId;
    }
}
