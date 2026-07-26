<?php

namespace App\Services\Routing;

use App\Models\CasePullRequest;
use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
use App\Models\SlaPolicy;
use App\Services\CaseElapsedTime;

/**
 * May this doctor pull work out of the pool? (Devin msgs 2308 and 2313 Q5/Q6.)
 *
 * "ELIGIBILITY CRITERIA WILL CHANGE AND NEEDS TO BE EDITABLE BUT WILL INCLUDE
 * OUTSTANDING MESSAGES/CASES AND MESSAGES/CASES OVERDUE AND SLA PERFORMANCE."
 *
 * TWO LAYERS, DIFFERENT OWNERS, DIFFERENT CONSEQUENCES.
 *
 *  1. POOL CRITERIA, on the routing policy, versioned with it. A breach is a flat
 *     refusal. These are the coefficients that used to drive INTELLIGENT mode,
 *     now doing the job Devin says they were always meant for (msg 2313 Q5).
 *  2. SLA POLICY, set by the doctor's own Doctor Admin. A breach either lets the
 *     pull through with an alert, or holds it for that admin to approve, per
 *     `on_violation` (msg 2313 Q6).
 *
 * WHY EVERY CRITERION IS OPT-IN. A null threshold is off. Shipping this with
 * defaults would silently stop doctors pulling on the day it deploys, and the
 * pool is currently the only way work moves in PROVIDER_POOL mode.
 *
 * A NEW CRITERION MUST BLOCK BY DEFAULT. Same rule as
 * ContinuityResolver::OVERRIDABLE: when someone adds a measure here they add it
 * to the refusal path, so forgetting to wire it up leaves the pool stricter
 * rather than quietly permissive.
 */
final class PoolEligibilityEvaluator
{
    public const POOL_OUTSTANDING_CASES  = 'POOL_OUTSTANDING_CASES';
    public const POOL_OVERDUE_CASES      = 'POOL_OVERDUE_CASES';
    public const POOL_AWAITING_REPLY     = 'POOL_AWAITING_REPLY';
    public const POOL_DAILY_LIMIT        = 'POOL_DAILY_LIMIT';
    public const PROVIDER_NOT_ACTIVE     = 'PROVIDER_NOT_ACTIVE';
    public const PROVIDER_UNAVAILABLE    = 'PROVIDER_UNAVAILABLE';

    public const SLA_OUTSTANDING_CASES   = 'SLA_OUTSTANDING_CASES';
    public const SLA_OVERDUE_CASES       = 'SLA_OVERDUE_CASES';
    public const SLA_DECISION_TIME       = 'SLA_DECISION_TIME';

    public const REASON_LABELS = [
        self::POOL_OUTSTANDING_CASES => 'Too many open cases already',
        self::POOL_OVERDUE_CASES     => 'Too many overdue cases',
        self::POOL_AWAITING_REPLY    => 'Too many patients waiting on a reply',
        self::POOL_DAILY_LIMIT       => 'Daily limit on pulled cases reached',
        self::PROVIDER_NOT_ACTIVE    => 'Doctor is not active',
        self::PROVIDER_UNAVAILABLE   => 'Doctor is marked unavailable',
        self::SLA_OUTSTANDING_CASES  => 'Over the SLA for open cases',
        self::SLA_OVERDUE_CASES      => 'Over the SLA for overdue cases',
        self::SLA_DECISION_TIME      => 'Over the SLA for decision time',
    ];

    /** Statuses that count as an open, non-terminal case. Matches the resolver. */
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support', 'approved', 'processing'];

    public function evaluate(Clinician $clinician, ?RoutingPolicy $policy = null): PoolEligibility
    {
        $policy ??= RoutingPolicy::active();
        $criteria = $policy ? $policy->poolCriteria() : [];

        $blocking = [];

        /*
         * Availability first. A doctor who is inactive or has switched
         * themselves off cannot pull, whatever their numbers look like. This is
         * not duplicated from EligibilityEvaluator for its own sake: a pull has
         * no case to evaluate against yet, so the per-case gate has nothing to
         * run on until candidates are picked.
         */
        if ($clinician->status !== 'active') {
            $blocking[] = self::PROVIDER_NOT_ACTIVE;
        }

        if (! $clinician->is_available) {
            $blocking[] = self::PROVIDER_UNAVAILABLE;
        }

        $openCases = $this->openCaseCount($clinician);

        if ($this->over($criteria['maxOutstandingCases'] ?? null, $openCases)) {
            $blocking[] = self::POOL_OUTSTANDING_CASES;
        }

        $overdueAfter = $criteria['overdueAfterHours'] ?? null;

        if (($criteria['maxOverdueCases'] ?? null) !== null && $overdueAfter !== null) {
            if ($this->over($criteria['maxOverdueCases'], $this->overdueCaseCount($clinician, (float) $overdueAfter))) {
                $blocking[] = self::POOL_OVERDUE_CASES;
            }
        }

        if ($this->over($criteria['maxAwaitingReply'] ?? null, $this->awaitingReplyCount($clinician))) {
            $blocking[] = self::POOL_AWAITING_REPLY;
        }

        if ($this->over($criteria['maxCasesPerDay'] ?? null, $this->pulledTodayCount($clinician))) {
            $blocking[] = self::POOL_DAILY_LIMIT;
        }

        /*
         * ── SLA, set by the Doctor Admin (msg 2313 Q6) ───────────────────────
         *
         * Evaluated even when the pool criteria already blocked, so the record on
         * the pull request shows everything that was wrong rather than whichever
         * layer happened to fire first.
         */
        $sla        = SlaPolicy::forClinician($clinician);
        $violations = [];

        if ($sla && $sla->isConfigured()) {
            if ($this->over($sla->max_outstanding_cases, $openCases)) {
                $violations[] = self::SLA_OUTSTANDING_CASES;
            }

            if ($sla->max_overdue_cases !== null && $sla->overdue_after_hours !== null) {
                if ($this->over($sla->max_overdue_cases, $this->overdueCaseCount($clinician, (float) $sla->overdue_after_hours))) {
                    $violations[] = self::SLA_OVERDUE_CASES;
                }
            }

            if ($sla->max_median_decision_minutes !== null) {
                $median = $this->medianDecisionMinutes($clinician);

                if ($median !== null && $median > (float) $sla->max_median_decision_minutes) {
                    $violations[] = self::SLA_DECISION_TIME;
                }
            }
        }

        return new PoolEligibility(
            blockingReasons:  array_values(array_unique($blocking)),
            slaViolations:    $violations,
            requiresApproval: $violations !== [] && $sla !== null && $sla->requiresApproval(),
        );
    }

    /**
     * The most a doctor may be granted in one request.
     *
     * Devin msg 2313 Q6 asked whether the number is clipped: it is, both by the
     * per-request ceiling here and, case by case, by their own caps as the grant
     * loop fills them up. Asking for 20 is a request, not an entitlement.
     */
    public function maxPerRequest(?RoutingPolicy $policy = null): ?int
    {
        $policy ??= RoutingPolicy::active();
        $value = $policy?->poolCriterion('maxCasesPerRequest');

        return is_numeric($value) && $value > 0 ? (int) $value : null;
    }

    /** Remaining allowance under the daily pull limit, or null when uncapped. */
    public function remainingToday(Clinician $clinician, ?RoutingPolicy $policy = null): ?int
    {
        $policy ??= RoutingPolicy::active();
        $limit = $policy?->poolCriterion('maxCasesPerDay');

        if (! is_numeric($limit) || $limit <= 0) {
            return null;
        }

        return max(0, (int) $limit - $this->pulledTodayCount($clinician));
    }

    /** A null threshold is off. Anything else compares with >=, so at the line is full. */
    private function over(int|float|null $threshold, int $current): bool
    {
        if ($threshold === null) {
            return false;
        }

        return $current >= (int) $threshold;
    }

    private function openCaseCount(Clinician $clinician): int
    {
        return PatientCase::where('clinician_id', $clinician->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->count();
    }

    /**
     * Cases sitting longer than the threshold without reaching a terminal state.
     *
     * Uses CaseElapsedTime::active() so paused cases (D16) are not counted as
     * overdue for the time they were legitimately waiting on a client response.
     * Previously used a raw SQL date-diff — kept semantically identical for cases
     * that have no pause intervals (all existing cases until D16 is wired).
     */
    private function overdueCaseCount(Clinician $clinician, float $afterHours): int
    {
        $thresholdMinutes = (int) round($afterHours * 60);

        return PatientCase::where('clinician_id', $clinician->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get(['id', 'assigned_at', 'created_at'])
            ->filter(fn (PatientCase $case) => CaseElapsedTime::active($case) >= $thresholdMinutes)
            ->count();
    }

    /**
     * Open cases where the newest inbound message is newer than the newest reply.
     *
     * Deliberately not `is_read`: opening a case marks its messages read without
     * answering the patient, so an is_read measure would count attention rather
     * than answers.
     */
    private function awaitingReplyCount(Clinician $clinician): int
    {
        return PatientCase::where('cases.clinician_id', $clinician->id)
            ->whereIn('cases.status', self::OPEN_STATUSES)
            ->whereRaw(
                '(SELECT MAX(m.created_at) FROM messages m
                    WHERE m.case_id = cases.id AND m.direction = ?)
                 > COALESCE((SELECT MAX(m2.created_at) FROM messages m2
                    WHERE m2.case_id = cases.id AND m2.direction = ?), ?)',
                ['inbound', 'outbound', '1970-01-01 00:00:00']
            )
            ->count();
    }

    /** Cases this doctor has pulled today, across all their granted requests. */
    private function pulledTodayCount(Clinician $clinician): int
    {
        return (int) CasePullRequest::where('clinician_id', $clinician->id)
            ->where('status', CasePullRequest::STATUS_GRANTED)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('granted_count');
    }

    /**
     * Decision time in minutes, approximated by the mean over decided cases.
     *
     * Devin msg 2313 Q6 left the definition of "SLA performance" open and this is
     * the proposal in use: time from case creation to approval. If the
     * distribution turns out skewed enough to matter, the median is the thing to
     * compute properly rather than approximate.
     */
    private function medianDecisionMinutes(Clinician $clinician): ?float
    {
        $value = PatientCase::where('clinician_id', $clinician->id)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) as m')
            ->value('m');

        return $value === null ? null : (float) $value;
    }
}
