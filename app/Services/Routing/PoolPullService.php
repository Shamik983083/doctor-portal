<?php

namespace App\Services\Routing;

use App\Models\CasePullRequest;
use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
use App\Models\User;
use App\Notifications\PoolPullApprovalNeeded;
use App\Notifications\PoolPullSlaBypassed;
use App\Services\CaseStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The provider pool, rebuilt as a pull queue (Devin msg 2308).
 *
 * "PROVIDER POOL: THESE FALL INTO A QUEUE AND AREN'T AUTO ASSIGNED. A PROVIDER
 * CAN REQUEST CASES (A NUMBER, THE DON'T SEE WHAT'S AVAILABLE). THEN IT CHECKES
 * EVERYTHING UP TOP AND LOOKS AT THE PROVIDER TO MAKE SURE THEY'RE ELIGIBLE TO BE
 * ASSIGNED CASES ... I.E. PROVIDER COULD SAY REQUEST CASES AND ASK FOR 20, SYSTEM
 * SHOULD CHECK STATES ELIGIBILE, CATEGORIES AND TYPE OF VISITS FROM AVAILABLE
 * POOL, MAKE SURE THEY AREN'T VIOLATING THE SET CRITERIA AND THEN TRANSFER CASES
 * TO THEM WITH THE OLDEST IN THE SYSTEM FIRST."
 *
 * THE SEQUENCE, in the order Devin gave it:
 *
 *  1. Check the doctor against the pool criteria and their SLA. A pool-criteria
 *     breach refuses outright; an SLA breach either passes with an alert or waits
 *     for their Doctor Admin, per SlaPolicy::on_violation.
 *  2. Walk the queue oldest first, and for each case run the SAME eligibility
 *     gate the push path uses: state, category, visit type, then the doctor's own
 *     caps. Reusing it is the point. A parallel implementation here would be free
 *     to drift into letting a pull do what a push refuses.
 *  3. Stop at the smallest of: what they asked for, what they are eligible for,
 *     the per-request ceiling, and their remaining headroom under their own caps.
 *  4. Claim each case atomically, so two doctors requesting at the same moment
 *     cannot both receive it.
 *
 * THE DOCTOR NEVER SEES THE QUEUE. Everything they learn comes back on their own
 * CasePullRequest row: how many they got, and if fewer than they asked for, why.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: it never overrides a cap. A pull is a
 * doctor asking for more work, which is the one situation where a cap is doing
 * exactly the job it was set for. Continuity overrides caps because that is the
 * patient's own doctor; nothing here is.
 */
final class PoolPullService
{
    /**
     * How deep into the queue to look for cases this doctor can take.
     *
     * Bounded because the walk builds requirements per case. A doctor asking for
     * 20 out of a queue where the oldest 500 are all in states they are not
     * licensed for should get a fast honest "none available", not a table scan.
     */
    private const SCAN_LIMIT = 300;

    public function __construct(
        private ?PoolEligibilityEvaluator $eligibility = null,
        private ?CaseStateMachine $stateMachine = null,
    ) {
        $this->eligibility ??= new PoolEligibilityEvaluator();
    }

    /**
     * A doctor asks the pool for work.
     *
     * Always returns a persisted CasePullRequest, including when the answer is
     * no. The refusal IS the deliverable: a doctor who cannot see the queue can
     * only find out why they got nothing from this row.
     */
    public function request(Clinician $clinician, int $requested): CasePullRequest
    {
        $policy    = RoutingPolicy::active();
        $requested = max(1, $requested);

        $eligibility = $this->eligibility->evaluate($clinician, $policy);

        if ($eligibility->isBlocked()) {
            return CasePullRequest::create([
                'clinician_id'     => $clinician->id,
                'requested_count'  => $requested,
                'granted_count'    => 0,
                'status'           => CasePullRequest::STATUS_REJECTED,
                'blocking_reasons' => $eligibility->allReasons(),
                'shortfall_reason' => 'Blocked by the pool eligibility criteria.',
            ]);
        }

        if ($eligibility->requiresApproval) {
            $request = CasePullRequest::create([
                'clinician_id'     => $clinician->id,
                'requested_count'  => $requested,
                'granted_count'    => 0,
                'status'           => CasePullRequest::STATUS_PENDING_APPROVAL,
                'blocking_reasons' => $eligibility->allReasons(),
                'shortfall_reason' => 'Held for Doctor Admin approval: over SLA.',
            ]);

            $this->notifyAdmins($clinician, fn ($admins) => $admins->each(
                fn ($admin) => $admin->notify(new PoolPullApprovalNeeded($request, $clinician))
            ));

            return $request;
        }

        $request = $this->grant($clinician, $requested, $policy, $eligibility->slaViolations);

        /*
         * BYPASS, the other half of Devin's Q6 answer. The doctor is over their
         * SLA and their admin chose not to stop them, so the pull went through
         * and the admin is told after the fact rather than asked before it.
         */
        if ($eligibility->slaViolations !== []) {
            $this->notifyAdmins($clinician, fn ($admins) => $admins->each(
                fn ($admin) => $admin->notify(new PoolPullSlaBypassed($request, $clinician))
            ));
        }

        return $request;
    }

    /** A Doctor Admin approves a held request, which grants it now. */
    public function approve(CasePullRequest $request, User $actor, ?string $note = null): CasePullRequest
    {
        if ($request->status !== CasePullRequest::STATUS_PENDING_APPROVAL) {
            return $request;
        }

        $granted = $this->grant(
            $request->clinician,
            $request->requested_count,
            RoutingPolicy::active(),
            $request->blocking_reasons ?? [],
            $request,
        );

        $granted->update([
            'decided_by'    => $actor->id,
            'decided_at'    => now(),
            'decision_note' => $note,
        ]);

        return $granted;
    }

    public function deny(CasePullRequest $request, User $actor, ?string $note = null): CasePullRequest
    {
        if ($request->status !== CasePullRequest::STATUS_PENDING_APPROVAL) {
            return $request;
        }

        $request->update([
            'status'        => CasePullRequest::STATUS_DENIED,
            'decided_by'    => $actor->id,
            'decided_at'    => now(),
            'decision_note' => $note,
        ]);

        return $request;
    }

    /**
     * Walk the queue and transfer what this doctor may take.
     *
     * @param  string[] $slaViolations recorded on the row for the audit trail
     */
    private function grant(
        Clinician $clinician,
        int $requested,
        ?RoutingPolicy $policy,
        array $slaViolations = [],
        ?CasePullRequest $existing = null,
    ): CasePullRequest {
        $clinician->loadMissing('acceptedCategories');

        $target    = $requested;
        $shortfall = [];

        $perRequest = $this->eligibility->maxPerRequest($policy);

        if ($perRequest !== null && $target > $perRequest) {
            $target      = $perRequest;
            $shortfall[] = "the per-request limit is {$perRequest}";
        }

        $remainingToday = $this->eligibility->remainingToday($clinician, $policy);

        if ($remainingToday !== null && $target > $remainingToday) {
            $target      = $remainingToday;
            $shortfall[] = "only {$remainingToday} left under today's pull limit";
        }

        $claimed = $target > 0 ? $this->claimOldestEligible($clinician, $target, $policy) : [];

        if (count($claimed) < $target) {
            $shortfall[] = 'no more cases in the queue matched this doctor\'s states, categories, visit types or remaining capacity';
        }

        $attributes = [
            'granted_count'    => count($claimed),
            'status'           => CasePullRequest::STATUS_GRANTED,
            'granted_case_ids' => $claimed,
            'blocking_reasons' => $slaViolations ?: null,
            'shortfall_reason' => count($claimed) < $requested && $shortfall !== []
                ? 'Asked for ' . $requested . ', granted ' . count($claimed) . ': ' . implode('; ', $shortfall) . '.'
                : null,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return CasePullRequest::create($attributes + [
            'clinician_id'    => $clinician->id,
            'requested_count' => $requested,
        ]);
    }

    /**
     * Take up to $target cases, oldest first, that this doctor is eligible for.
     *
     * THE WORKLOAD COUNTERS RUN AS WE GO. Each grant increments the doctor's
     * open, daily and new-case counts before the next case is evaluated, so a
     * doctor two cases from their cap gets two cases and not twenty. Evaluating
     * every case against the counts as they were at the start would let a single
     * pull blow through every cap the push path enforces.
     *
     * @return int[] ids of the cases transferred
     */
    private function claimOldestEligible(Clinician $clinician, int $target, ?RoutingPolicy $policy): array
    {
        $counts    = $this->currentCounts($clinician);
        $maxDaily  = ((int) $clinician->max_daily_cases) > 0 ? (int) $clinician->max_daily_cases : null;
        $maxOpen   = $clinician->maxOpenCasesOrNull();
        $maxNew    = $clinician->maxDailyNewCasesOrNull();
        $threshold = $policy?->messageAgingThresholdHours();
        $requireLicensure = $policy ? $policy->requireRecordedLicensure() : true;

        $queue = PatientCase::with(['offerings', 'patient'])
            ->whereNull('clinician_id')
            ->where('status', PatientCase::STATUS_WAITING)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::SCAN_LIMIT)
            ->get();

        $claimed = [];

        foreach ($queue as $case) {
            if (count($claimed) >= $target) {
                break;
            }

            $requirements = CaseRequirements::fromCase($case);

            if (! $requirements->hasCategory()) {
                continue;
            }

            $reasons = EligibilityEvaluator::evaluate(
                $clinician,
                $requirements->state,
                [
                    'maxDailyVolume'                  => $maxDaily,
                    'currentDailyVolume'              => $counts['daily'],
                    'maxOpenCases'                    => $maxOpen,
                    'currentOpenCases'                => $counts['open'],
                    'oldestUnansweredMessageAgeHours' => null,
                    'isNewCase'                       => $requirements->isNewCase,
                    'acceptingNewCases'               => (bool) $clinician->accepting_new_cases,
                    'maxDailyNewCases'                => $maxNew,
                    'currentDailyNewCases'            => $counts['newToday'],
                ],
                $threshold,
                $requireLicensure,
                $requirements,
            );

            if ($reasons !== []) {
                continue;
            }

            if (! $this->claim($case, $clinician)) {
                continue;       // somebody else took it first
            }

            $claimed[] = $case->id;
            $counts['daily']++;
            $counts['open']++;

            if ($requirements->isNewCase) {
                $counts['newToday']++;
            }
        }

        return $claimed;
    }

    /**
     * Stake a claim on one case, atomically.
     *
     * The conditional UPDATE is the whole mechanism: `whereNull('clinician_id')`
     * means exactly one of two simultaneous requests can affect a row, and the
     * loser sees 0 affected and moves on. Doing this with a read-then-write would
     * hand the same case to both doctors under any real concurrency.
     *
     * The state transition runs inside the same transaction, so a case that
     * cannot legally move to assigned releases the claim rather than sitting
     * owned by a doctor whose queue never shows it.
     */
    private function claim(PatientCase $case, Clinician $clinician): bool
    {
        try {
            return DB::transaction(function () use ($case, $clinician) {
                $staked = PatientCase::where('id', $case->id)
                    ->whereNull('clinician_id')
                    ->update(['clinician_id' => $clinician->id]);

                if ($staked !== 1) {
                    return false;
                }

                $fresh = PatientCase::find($case->id);

                $this->stateMachine()->transition($fresh, PatientCase::STATUS_ASSIGNED, [
                    'clinician_id' => $clinician->id,
                    'source'       => 'pool_pull',
                ]);

                // B3: stamp the completion deadline so the sweep command can warn
                // and auto-release when the provider misses the window.
                $hours = (int) config('routing.completion_deadline_hours', 24);
                $fresh->updateQuietly(['completion_deadline_at' => now()->addHours($hours)]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('Pool pull could not claim case', [
                'case_id'      => $case->id,
                'clinician_id' => $clinician->id,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array{daily:int, open:int, newToday:int} */
    private function currentCounts(Clinician $clinician): array
    {
        $openStatuses = ['waiting', 'assigned', 'support', 'approved', 'processing'];

        return [
            'open' => PatientCase::where('clinician_id', $clinician->id)
                ->whereIn('status', $openStatuses)
                ->count(),
            'daily' => PatientCase::where('clinician_id', $clinician->id)
                ->whereNotNull('assigned_at')
                ->where('assigned_at', '>=', now()->startOfDay())
                ->count(),
            'newToday' => PatientCase::where('clinician_id', $clinician->id)
                ->where('is_refill', false)
                ->whereNotNull('assigned_at')
                ->where('assigned_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }

    /**
     * Resolved lazily out of the container.
     *
     * CaseStateMachine takes three collaborators of its own, so constructing one
     * eagerly would make this service impossible to instantiate in a test that
     * never claims anything.
     */
    private function stateMachine(): CaseStateMachine
    {
        return $this->stateMachine ??= app(CaseStateMachine::class);
    }

    /** Send something to this doctor's admins, falling back to the admin pool. */
    private function notifyAdmins(Clinician $clinician, callable $send): void
    {
        try {
            $admins = $clinician->admins()->get();

            if ($admins->isEmpty()) {
                $admins = User::role(['admin', 'super_admin'])->get();
            }

            $send($admins);
        } catch (\Throwable $e) {
            // Never break a pull because a notification failed. Moving work is
            // the job; telling an admin about it is not.
            Log::warning('Pool pull notification failed: ' . $e->getMessage(), [
                'clinician_id' => $clinician->id,
            ]);
        }
    }
}
