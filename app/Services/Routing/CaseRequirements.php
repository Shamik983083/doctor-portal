<?php

namespace App\Services\Routing;

use App\Models\PatientCase;

/**
 * The three facts a case brings to the eligibility gate (Devin msg 2308).
 *
 * "SO THE CAVEAT FOR ANY OF THESE IS IT MUST CHECK THE STATE THE PRESCRIPTION IS
 * NEEDED IN, PRODUCT CATEGORY ... AND WHAT TYPE OF VISIT (ASYNCHRONOUS VS
 * SYNCHRONOUS), THEN DEFER TO CLINICIANS AVAILABLE IN THAT STATE, AND THEN IF
 * THEY'RE OPEN FOR THE TYPE"
 *
 * Gathered ONCE per routing decision and handed to every candidate evaluation,
 * rather than re-derived per doctor. The visit type in particular costs a query
 * against the state matrix, and doing that once per doctor per case is the kind
 * of thing that is invisible in staging and expensive in production.
 *
 * No PHI: a state code, some ids, and a visit type.
 */
final class CaseRequirements
{
    public function __construct(
        public readonly ?string $state,
        /** @var int[] Offering ids on the case. */
        public readonly array $offeringIds = [],
        /** @var int[] Distinct category ids behind those offerings. */
        public readonly array $categoryIds = [],
        public readonly string $visitType = VisitType::ASYNCHRONOUS,
        /** A first visit, as opposed to a check-in. Selects the path. */
        public readonly bool $isNewCase = true,
    ) {}

    /**
     * Build from a case.
     *
     * The state is the one the PRESCRIPTION is needed in: `cases.patient_state`
     * where the partner sent it, falling back to the patient record. Not the
     * patient's billing address, which is a different question nobody is asking.
     */
    public static function fromCase(PatientCase $case, ?StateVisitRequirementResolver $visits = null): self
    {
        $visits ??= new StateVisitRequirementResolver();

        $state = $case->patient_state ?: $case->patient?->state;

        $offerings = $case->relationLoaded('offerings')
            ? $case->offerings
            : $case->offerings()->get();

        $offeringIds = $offerings->pluck('id')->map(fn ($id) => (int) $id)->all();

        $categoryIds = $offerings
            ->pluck('category_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return new self(
            state:       $state,
            offeringIds: $offeringIds,
            categoryIds: $categoryIds,
            visitType:   $visits->resolve($state, $offeringIds, $categoryIds),
            isNewCase:   ! $case->isRefillRequest(),
        );
    }

    /**
     * A case with no category cannot be matched to a doctor at all.
     *
     * Reported as its own exception code rather than as "nobody was eligible",
     * because the fix is on the case or the offering, not on any doctor's
     * configuration, and the two would otherwise be indistinguishable on the
     * exceptions screen.
     */
    public function hasCategory(): bool
    {
        return $this->categoryIds !== [];
    }

    public function requiresSynchronous(): bool
    {
        return $this->visitType === VisitType::SYNCHRONOUS;
    }
}
