<?php

namespace Tests\Unit;

use App\Models\Clinician;
use App\Models\OfferingCategory;
use App\Services\Routing\CaseRequirements;
use App\Services\Routing\EligibilityEvaluator;
use App\Services\Routing\VisitType;
use Tests\TestCase;

/**
 * The eligibility gate: state, product category, visit type (Devin msg 2308).
 *
 * The rules being pinned here:
 *   - all three axes are HARD blocks, checked before any workload consideration;
 *   - a doctor must accept EVERY category on the case, not just one of them;
 *   - a synchronous case needs both the switch AND a booking link;
 *   - blank licensure now REJECTS (msg 2313), where it used to read as licensed
 *     everywhere;
 *   - none of the three is in ContinuityResolver::OVERRIDABLE, so a check-in is
 *     blocked by them exactly as a first visit is.
 *
 * Database-free. EligibilityEvaluator is pure, and the accepted-categories
 * relation is set by hand with setRelation() so no query is needed.
 */
class EligibilityGateTest extends TestCase
{
    private function clinician(array $attrs = [], array $categoryIds = [1]): Clinician
    {
        $clinician = new Clinician(array_merge([
            'status'               => 'active',
            'is_available'         => true,
            'licensed_states'      => [['state' => 'CA']],
            'accepts_async_visits' => true,
            'accepts_sync_visits'  => false,
            'scheduling_link'      => null,
        ], $attrs));

        $clinician->setRelation('acceptedCategories', collect(
            array_map(fn ($id) => (new OfferingCategory())->forceFill(['id' => $id]), $categoryIds)
        ));

        return $clinician;
    }

    private function workload(array $over = []): array
    {
        return array_merge([
            'maxDailyVolume'                  => null,
            'currentDailyVolume'              => 0,
            'maxOpenCases'                    => null,
            'currentOpenCases'                => 0,
            'oldestUnansweredMessageAgeHours' => null,
            'isNewCase'                       => true,
            'acceptingNewCases'               => true,
        ], $over);
    }

    private function requirements(array $over = []): CaseRequirements
    {
        return new CaseRequirements(
            state:       $over['state']       ?? 'CA',
            offeringIds: $over['offeringIds'] ?? [10],
            categoryIds: $over['categoryIds'] ?? [1],
            visitType:   $over['visitType']   ?? VisitType::ASYNCHRONOUS,
            isNewCase:   $over['isNewCase']   ?? true,
        );
    }

    private function evaluate(Clinician $clinician, ?CaseRequirements $requirements, array $workload = []): array
    {
        return EligibilityEvaluator::evaluate(
            $clinician,
            $requirements?->state ?? 'CA',
            $this->workload($workload),
            null,
            true,
            $requirements,
        );
    }

    /* ── Axis 2: product category ─────────────────────────────────────────── */

    public function test_doctor_who_accepts_the_category_passes(): void
    {
        $reasons = $this->evaluate($this->clinician([], [1]), $this->requirements(['categoryIds' => [1]]));

        $this->assertSame([], $reasons);
    }

    public function test_doctor_who_does_not_accept_the_category_is_blocked(): void
    {
        $reasons = $this->evaluate($this->clinician([], [2]), $this->requirements(['categoryIds' => [1]]));

        $this->assertContains(EligibilityEvaluator::CATEGORY_NOT_ACCEPTED, $reasons);
    }

    public function test_doctor_must_accept_every_category_on_the_case(): void
    {
        // The case carries two products. Accepting one of them is not enough:
        // it is a single prescribing decision and half of it cannot be taken.
        $reasons = $this->evaluate($this->clinician([], [1]), $this->requirements(['categoryIds' => [1, 2]]));

        $this->assertContains(EligibilityEvaluator::CATEGORY_NOT_ACCEPTED, $reasons);
    }

    public function test_empty_accepted_categories_blocks_everything(): void
    {
        // Fail closed, matching the position taken on blank licensure. The
        // migration backfills every existing doctor so this default cannot
        // strand anyone who has not been configured yet.
        $reasons = $this->evaluate($this->clinician([], []), $this->requirements());

        $this->assertContains(EligibilityEvaluator::CATEGORY_NOT_ACCEPTED, $reasons);
    }

    /* ── Axis 3: visit type ───────────────────────────────────────────────── */

    public function test_synchronous_case_blocked_for_async_only_doctor(): void
    {
        $reasons = $this->evaluate(
            $this->clinician(['accepts_sync_visits' => false]),
            $this->requirements(['visitType' => VisitType::SYNCHRONOUS]),
        );

        $this->assertContains(EligibilityEvaluator::VISIT_TYPE_NOT_ACCEPTED, $reasons);
    }

    public function test_synchronous_case_blocked_when_doctor_has_no_booking_link(): void
    {
        // Its own reason code, because the fix is different: this doctor said yes
        // and simply cannot be booked yet.
        $reasons = $this->evaluate(
            $this->clinician(['accepts_sync_visits' => true, 'scheduling_link' => null]),
            $this->requirements(['visitType' => VisitType::SYNCHRONOUS]),
        );

        $this->assertContains(EligibilityEvaluator::SCHEDULING_LINK_MISSING, $reasons);
        $this->assertNotContains(EligibilityEvaluator::VISIT_TYPE_NOT_ACCEPTED, $reasons);
    }

    public function test_synchronous_case_passes_with_switch_and_link(): void
    {
        $reasons = $this->evaluate(
            $this->clinician([
                'accepts_sync_visits' => true,
                'scheduling_link'     => 'https://calendly.com/dr-example/visit',
            ]),
            $this->requirements(['visitType' => VisitType::SYNCHRONOUS]),
        );

        $this->assertSame([], $reasons);
    }

    public function test_async_case_blocked_for_doctor_who_does_not_take_async(): void
    {
        $reasons = $this->evaluate(
            $this->clinician(['accepts_async_visits' => false]),
            $this->requirements(['visitType' => VisitType::ASYNCHRONOUS]),
        );

        $this->assertContains(EligibilityEvaluator::VISIT_TYPE_NOT_ACCEPTED, $reasons);
    }

    /* ── Axis 1: licensure, now fail-closed ───────────────────────────────── */

    public function test_blank_licensure_now_rejects(): void
    {
        // Devin msg 2313: "empty should not show licensed everywhere it needs to
        // reject". This used to pass.
        $reasons = $this->evaluate($this->clinician(['licensed_states' => []]), $this->requirements());

        $this->assertContains(EligibilityEvaluator::LICENSURE_NOT_RECORDED, $reasons);
    }

    public function test_licence_in_another_state_is_a_different_reason_from_blank(): void
    {
        $reasons = $this->evaluate(
            $this->clinician(['licensed_states' => [['state' => 'TX']]]),
            $this->requirements(['state' => 'CA']),
        );

        $this->assertContains(EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING, $reasons);
        $this->assertNotContains(EligibilityEvaluator::LICENSURE_NOT_RECORDED, $reasons);
    }

    /* ── The gate applies to check-ins too (msg 2313 Q3) ──────────────────── */

    public function test_new_axes_block_a_check_in_as_firmly_as_a_first_visit(): void
    {
        $reasons = $this->evaluate(
            $this->clinician([], [2]),
            $this->requirements(['categoryIds' => [1], 'isNewCase' => false]),
            ['isNewCase' => false],
        );

        $this->assertContains(EligibilityEvaluator::CATEGORY_NOT_ACCEPTED, $reasons);
    }

    public function test_new_axes_are_not_overridable_by_continuity(): void
    {
        // The inverse allow-list is what makes this automatic. If any of these
        // three ever appears in OVERRIDABLE, a doctor who stopped taking a
        // category keeps receiving check-ins on it.
        $overridable = \App\Services\Routing\ContinuityResolver::OVERRIDABLE;

        $this->assertNotContains(EligibilityEvaluator::CATEGORY_NOT_ACCEPTED, $overridable);
        $this->assertNotContains(EligibilityEvaluator::VISIT_TYPE_NOT_ACCEPTED, $overridable);
        $this->assertNotContains(EligibilityEvaluator::SCHEDULING_LINK_MISSING, $overridable);
    }

    /* ── Backwards compatibility ──────────────────────────────────────────── */

    public function test_no_requirements_supplied_skips_the_two_new_axes(): void
    {
        // Older callers that pass no CaseRequirements must keep working exactly
        // as before, or every existing path breaks the moment this ships.
        $reasons = $this->evaluate($this->clinician([], []), null);

        $this->assertSame([], $reasons);
    }
}
