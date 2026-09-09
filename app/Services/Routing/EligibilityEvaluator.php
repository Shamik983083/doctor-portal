<?php

namespace App\Services\Routing;

use App\Models\Clinician;

/**
 * Provider eligibility: may this doctor take this case?
 *
 * Ported from MA-DOCPORTAL `packages/domain/src/eligibility.ts`. Pure, no I/O:
 * the caller supplies the facts.
 *
 * FAIL CLOSED THROUGHOUT, exactly as MA has it. This is an authorization surface,
 * so it returns a reason for EVERY block that fired, not just the first:
 *   - only an explicit active status passes;
 *   - a cap that cannot be evaluated BLOCKS rather than passing;
 *   - a missing licence blocks, and so does blank licensure.
 *
 * ── THE ELIGIBILITY GATE (Devin msg 2308) ────────────────────────────────────
 *
 * "SO THE CAVEAT FOR ANY OF THESE IS IT MUST CHECK THE STATE THE PRESCRIPTION IS
 * NEEDED IN, PRODUCT CATEGORY ... AND WHAT TYPE OF VISIT (ASYNCHRONOUS VS
 * SYNCHRONOUS), THEN DEFER TO CLINICIANS AVAILABLE IN THAT STATE, AND THEN IF
 * THEY'RE OPEN FOR THE TYPE AND THEN TIE IN TO THE OPTIONS BELOW"
 *
 * Three axes are checked BEFORE anything about the doctor's workload, in Devin's
 * order: state, then category, then visit type. All three are hard blocks that no
 * mode can score past and, per his Q3 answer, that continuity may not override
 * either: they are clinical and legal scope, not workload. When they block a
 * check-in, the case falls through to normal routing and "route them to a new
 * provider" (his words) is what happens.
 *
 * The order is deliberate. Every reason that fires is returned, so ordering is
 * not about which block wins, it is about which one is read first by a human
 * looking at the exceptions queue. The most legally consequential comes first.
 *
 * ── BLANK LICENSURE NOW BLOCKS (Devin msg 2313) ──────────────────────────────
 *
 * "empty should not show licensed everywhere it needs to reject". Previously a
 * doctor with no recorded licensed states read as licensed everywhere and this
 * whole gate did nothing for them. `Clinician::isLicensedInState()` is fail-closed
 * now and `requireRecordedLicensure` defaults true, so blank licensure produces
 * LICENSURE_NOT_RECORDED. Run `php artisan licensure:audit` before deploying:
 * every clinician it lists stops receiving cases.
 */
final class EligibilityEvaluator
{
    public const PROVIDER_NOT_ACTIVE               = 'PROVIDER_NOT_ACTIVE';
    public const PROVIDER_UNAVAILABLE              = 'PROVIDER_UNAVAILABLE';
    public const RESIDENCE_STATE_LICENSE_MISSING   = 'RESIDENCE_STATE_LICENSE_MISSING';
    public const LICENSE_EXPIRED_IN_STATE          = 'LICENSE_EXPIRED_IN_STATE';
    public const LICENSURE_NOT_RECORDED            = 'LICENSURE_NOT_RECORDED';
    public const DAILY_VOLUME_CAP_REACHED          = 'DAILY_VOLUME_CAP_REACHED';
    public const OPEN_CASES_CAP_REACHED            = 'OPEN_CASES_CAP_REACHED';
    public const MESSAGE_AGING_BLOCK               = 'MESSAGE_AGING_BLOCK';

    /*
     * THE TWO NEW AXES (Devin msg 2308). Both are clinical scope rather than
     * workload, so both sit outside ContinuityResolver::OVERRIDABLE and block a
     * check-in as firmly as a first visit.
     */
    public const CATEGORY_NOT_ACCEPTED             = 'CATEGORY_NOT_ACCEPTED';
    public const VISIT_TYPE_NOT_ACCEPTED           = 'VISIT_TYPE_NOT_ACCEPTED';
    public const SCHEDULING_LINK_MISSING           = 'SCHEDULING_LINK_MISSING';

    /*
     * NEW-CASE-ONLY BLOCKS (Devin msgs 2248/2250). These fire for a first visit
     * and NEVER for a check-in: a returning patient still reaches their own
     * doctor even when that doctor has stopped taking new patients. They are
     * gated on $isNewCase below and are absent from every refill path.
     */
    public const NOT_ACCEPTING_NEW_CASES           = 'NOT_ACCEPTING_NEW_CASES';
    public const DAILY_NEW_CASE_CAP_REACHED        = 'DAILY_NEW_CASE_CAP_REACHED';
    public const DELAYED_CASES_THRESHOLD           = 'DELAYED_CASES_THRESHOLD';
    public const AWAITING_REPLY_THRESHOLD          = 'AWAITING_REPLY_THRESHOLD';

    public const REASON_LABELS = [
        self::PROVIDER_NOT_ACTIVE             => 'Doctor is not active',
        self::PROVIDER_UNAVAILABLE            => 'Doctor is marked unavailable',
        self::RESIDENCE_STATE_LICENSE_MISSING => 'No licence in the patient\'s state',
        self::LICENSE_EXPIRED_IN_STATE        => 'Licence in this state has expired — renewal required',
        self::LICENSURE_NOT_RECORDED          => 'No licensed states recorded for this doctor',
        self::CATEGORY_NOT_ACCEPTED           => 'Doctor does not accept this product category',
        self::VISIT_TYPE_NOT_ACCEPTED         => 'Doctor does not take this type of visit',
        self::SCHEDULING_LINK_MISSING         => 'Doctor has no booking link for synchronous visits',
        self::DAILY_VOLUME_CAP_REACHED        => 'Daily case cap reached',
        self::OPEN_CASES_CAP_REACHED          => 'Open case cap reached',
        self::MESSAGE_AGING_BLOCK             => 'Has a message older than the configured limit',
        self::NOT_ACCEPTING_NEW_CASES         => 'Not accepting new cases (books full)',
        self::DAILY_NEW_CASE_CAP_REACHED      => 'Daily new-case cap reached',
        self::DELAYED_CASES_THRESHOLD         => 'Too many delayed cases to take new work',
        self::AWAITING_REPLY_THRESHOLD        => 'Too many cases awaiting a reply to take new work',
    ];

    /**
     * @param  array{
     *     currentDailyVolume:int, currentOpenCases:int, maxDailyVolume:?int, maxOpenCases:?int,
     *     oldestUnansweredMessageAgeHours:?float,
     *     isNewCase?:bool, acceptingNewCases?:bool,
     *     maxDailyNewCases?:?int, currentDailyNewCases?:int,
     *     maxDelayedCases?:?int, currentDelayedCases?:int,
     *     maxAwaitingReply?:?int, currentAwaitingReply?:int
     *  } $workload
     * @return string[] every reason that fired, in a fixed order
     */
    public static function evaluate(
        Clinician $clinician,
        ?string $residenceState,
        array $workload,
        ?float $messageAgingThresholdHours = null,
        bool $requireRecordedLicensure = true,
        ?CaseRequirements $requirements = null,
    ): array {
        $reasons = [];

        /*
         * ── AXIS 1: THE STATE THE PRESCRIPTION IS NEEDED IN ──────────────────
         *
         * A missing patient state is NOT a free pass. If we do not know where the
         * patient is, we cannot show the doctor is licensed to treat them, and this
         * is a fail-closed surface. Blocking a routable case is recoverable;
         * routing to an unlicensed prescriber is not.
         */
        if ($residenceState === null || trim($residenceState) === '') {
            $reasons[] = self::RESIDENCE_STATE_LICENSE_MISSING;
        } else {
            $state = strtoupper(trim($residenceState));

            /*
             * Blank licensure is its own reason code, distinct from "licensed,
             * but not here". They look the same to routing and completely
             * different to whoever has to fix it: one is a data-entry job, the
             * other means recruiting in that state.
             */
            $hasRecordedLicensure = ! empty($clinician->licensed_states);

            if (! $hasRecordedLicensure) {
                if ($requireRecordedLicensure) {
                    $reasons[] = self::LICENSURE_NOT_RECORDED;
                }
            } elseif ($clinician->isLicenseExpiredInState($state)) {
                // Expired is checked before missing so the admin sees "renewal
                // required" rather than "no licence", which would imply recruiting.
                $reasons[] = self::LICENSE_EXPIRED_IN_STATE;
            } elseif (! $clinician->isLicensedInState($state)) {
                $reasons[] = self::RESIDENCE_STATE_LICENSE_MISSING;
            }
        }

        /*
         * ── AXIS 2: PRODUCT CATEGORY ─────────────────────────────────────────
         *
         * "PROVIDER SHOULD HAVE A SECTION FOR CATEGORIES THEY'LL ACCEPT, WE MUST
         * BE ABLE TO ADJUST AND SET" (msg 2308).
         *
         * The doctor must accept EVERY category on the case, not merely one of
         * them. A case carrying two products is one prescribing decision, and a
         * doctor who takes GLP1 but not peptides cannot take half of it.
         *
         * Skipped entirely when no requirements were supplied, which is how the
         * continuity path and older callers keep working unchanged.
         */
        if ($requirements !== null && $requirements->categoryIds !== []) {
            foreach ($requirements->categoryIds as $categoryId) {
                if (! $clinician->acceptsCategory($categoryId)) {
                    $reasons[] = self::CATEGORY_NOT_ACCEPTED;
                    break;
                }
            }
        }

        /*
         * ── AXIS 3: VISIT TYPE ───────────────────────────────────────────────
         *
         * Synchronous is decided by the state matrix, never by the doctor. What
         * the doctor decides is whether they take those visits, and a booking
         * link is part of taking them: a live visit nobody can book is not an
         * assignment, it is a case the patient discovers is stuck.
         *
         * The missing-link case gets its own reason code because the fix is
         * different. VISIT_TYPE_NOT_ACCEPTED means "this doctor said no";
         * SCHEDULING_LINK_MISSING means "this doctor said yes and cannot deliver
         * it yet", which is a five-second fix on their profile.
         */
        if ($requirements !== null && $requirements->requiresSynchronous()) {
            if (! $clinician->accepts_sync_visits) {
                $reasons[] = self::VISIT_TYPE_NOT_ACCEPTED;
            } elseif (! $clinician->hasSchedulingLink()) {
                $reasons[] = self::SCHEDULING_LINK_MISSING;
            }
        } elseif ($requirements !== null && ! $clinician->accepts_async_visits) {
            $reasons[] = self::VISIT_TYPE_NOT_ACCEPTED;
        }

        /*
         * ── THEN THE DOCTOR'S OWN AVAILABILITY AND WORKLOAD ──────────────────
         */
        if ($clinician->status !== 'active') {
            $reasons[] = self::PROVIDER_NOT_ACTIVE;
        }

        if (! $clinician->is_available) {
            $reasons[] = self::PROVIDER_UNAVAILABLE;
        }

        if (self::capReached($workload['maxDailyVolume'] ?? null, $workload['currentDailyVolume'] ?? null)) {
            $reasons[] = self::DAILY_VOLUME_CAP_REACHED;
        }

        if (self::capReached($workload['maxOpenCases'] ?? null, $workload['currentOpenCases'] ?? null)) {
            $reasons[] = self::OPEN_CASES_CAP_REACHED;
        }

        $oldest = $workload['oldestUnansweredMessageAgeHours'] ?? null;

        if ($messageAgingThresholdHours !== null && $oldest !== null && $oldest >= $messageAgingThresholdHours) {
            $reasons[] = self::MESSAGE_AGING_BLOCK;
        }

        /*
         * NEW-CASE-ONLY BLOCKS (Devin msgs 2248/2250).
         *
         * A check-in must still reach the doctor who treated the patient, so
         * none of these may fire for a refill. The gate is a single flag: for a
         * check-in the caller passes isNewCase=false and this whole block is
         * skipped. A refill therefore never carries a "books full" or
         * "too delayed" reason, and continuity is never blocked by capacity.
         *
         * "Books full" is a per-doctor switch; the delayed and awaiting-reply
         * thresholds are admin-set on the routing policy. All are opt-in: a null
         * threshold or an unset flag does nothing.
         */
        if (($workload['isNewCase'] ?? true) === true) {
            if (($workload['acceptingNewCases'] ?? true) === false) {
                $reasons[] = self::NOT_ACCEPTING_NEW_CASES;
            }

            if (self::capReached($workload['maxDailyNewCases'] ?? null, $workload['currentDailyNewCases'] ?? null)) {
                $reasons[] = self::DAILY_NEW_CASE_CAP_REACHED;
            }

            if (self::capReached($workload['maxDelayedCases'] ?? null, $workload['currentDelayedCases'] ?? null)) {
                $reasons[] = self::DELAYED_CASES_THRESHOLD;
            }

            if (self::capReached($workload['maxAwaitingReply'] ?? null, $workload['currentAwaitingReply'] ?? null)) {
                $reasons[] = self::AWAITING_REPLY_THRESHOLD;
            }
        }

        return $reasons;
    }

    /**
     * Is a configured cap reached?
     *
     * null max means uncapped, and is the ONLY value that legitimately passes.
     * Everything else is fail-closed: a non-null max or a current count that is not
     * a finite number is treated as corrupt input and BLOCKS. A bare `$current >=
     * $max` would return false for null or NAN and quietly pass a block it could
     * not evaluate, which on an authorization surface is the wrong default.
     */
    private static function capReached(?int $max, ?int $current): bool
    {
        if ($max === null) {
            return false;                 // uncapped
        }

        if (! is_int($current)) {
            return true;                  // cannot evaluate, so block
        }

        return $current >= $max;
    }
}
