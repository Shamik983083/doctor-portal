<?php

namespace App\Services\Routing;

use App\Models\Clinician;
use App\Models\Message;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Gathers the real workload facts for every doctor and runs the strategy.
 *
 * Ported from MA-DOCPORTAL's LiveRoutingPolicyResolver + PrismaRoutingDataSource.
 * This is the I/O half; RoutingStrategy is the pure half.
 *
 * NOTE ON WHAT MEDAXIS CAN DO THAT MA COULD NOT. MA's resolver hard-codes
 * `unansweredMessages: 0` and `medianDecisionMinutes: 0` with the comment that no
 * message store or decision-timing rollup exists yet, so two of its intelligent
 * coefficients score zero for everyone and do nothing. MEDAXIS HAS both: a
 * `messages` table with direction, and `created_at`/`approved_at` on cases. So
 * those signals are wired to real data here and the INTELLIGENT mode is more
 * complete in MEDAXIS than in the system it was ported from.
 */
final class RoutingPolicyResolver
{
    /** Statuses that count as an open, non-terminal case. */
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support', 'approved', 'processing'];

    private ContinuityResolver $continuity;

    public function __construct(?ContinuityResolver $continuity = null)
    {
        $this->continuity = $continuity ?? new ContinuityResolver();
    }

    /**
     * Decide where a case goes.
     *
     * @return array{kind:string, providerId?:int, reason?:string} ASSIGN / POOL / NONE
     */
    public function resolve(PatientCase $case, ?RoutingPolicy $policy = null): array
    {
        $policy ??= RoutingPolicy::active();

        // No active policy: assign nobody. MA's posture, and the safe one. A
        // built-in fallback would route under rules nobody chose.
        //
        // Reported as its own systemic exception code: this is not "no doctor
        // could take this case", it is "nothing in the system is being assigned
        // at all", and those must not look the same on the exceptions screen.
        if (! $policy) {
            return [
                'kind'       => RoutingStrategy::NONE,
                'reasonCode' => \App\Models\RoutingException::NO_ACTIVE_POLICY,
            ];
        }

        /*
         * THE ELIGIBILITY GATE'S FACTS, GATHERED ONCE (Devin msg 2308).
         *
         * State, product categories and visit type for this case. Built here
         * rather than per candidate because the visit type costs a query against
         * the state matrix, and doing that once per doctor per case is the kind
         * of cost that hides in staging.
         */
        $requirements = CaseRequirements::fromCase($case);

        /*
         * CONTINUITY OF CARE RUNS FIRST (Devin msg 2244).
         *
         * A check-in goes back to the doctor who treated this patient, when they
         * can still take it. It sits ahead of the strategy because all five
         * modes answer "who is least loaded right now", and for a returning
         * patient that is the wrong question.
         *
         * PROVIDER_POOL is honoured OVER continuity on purpose: that mode means
         * "push nothing, every case is claimed", and quietly pushing check-ins
         * would make the pool not a pool. Continuity still happens there, as the
         * doctor recognising their own patient in the queue.
         *
         * Returns null for anything that is not a check-in, so first visits
         * route exactly as they did before this existed.
         */
        /*
         * THE TWO PATHS (Devin msg 2308). A check-in and a first visit no longer
         * share a mode: the policy carries one for each, and this is where the
         * case picks its lane. An older version with no per-path config reads
         * through to the single `mode` column, so v1 behaves exactly as before.
         */
        $mode = $policy->modeForCase($requirements->isNewCase);

        if ($mode !== RoutingMode::PROVIDER_POOL) {
            $continuous = $this->continuity->resolve(
                $case,
                $policy->requireRecordedLicensure(),
                $requirements,
            );

            if ($continuous) {
                return [
                    'kind'       => RoutingStrategy::ASSIGN,
                    'providerId' => $continuous->id,
                    'reason'     => 'CONTINUITY_OF_CARE',
                ];
            }
        }

        /*
         * A case with no product category cannot be matched against any doctor's
         * accepted-category list, so it is reported as a fault on the case rather
         * than as "nobody was eligible". The fix is on the offering, not on any
         * doctor's configuration, and conflating the two sends whoever reads the
         * exceptions queue to the wrong screen.
         */
        if (! $requirements->hasCategory()) {
            return [
                'kind'       => RoutingStrategy::NONE,
                'reasonCode' => \App\Models\RoutingException::NO_CATEGORY_ON_CASE,
            ];
        }

        $candidates = $this->candidates($case, $policy, $requirements);

        $outcome = RoutingStrategy::select(
            $mode,
            $candidates,
            $policy->intelligentWeights(),
            ['roundRobinCursor' => $this->roundRobinCursor()],
        );

        /*
         * NO SILENT FAILURES (Devin msg 2308). When nobody could take the case,
         * carry the per-doctor block reasons out with the answer so the caller can
         * record WHY rather than a bare "unassigned". An unknown mode is reported
         * separately: it means the stored policy is malformed and NOTHING is being
         * routed, which is a different emergency from a case nobody is licensed for.
         */
        if (($outcome['kind'] ?? null) === RoutingStrategy::NONE) {
            $outcome['reasonCode'] = in_array($mode, RoutingMode::ALL_STORED, true)
                ? \App\Models\RoutingException::NO_ELIGIBLE_PROVIDER
                : \App\Models\RoutingException::UNKNOWN_MODE;

            $outcome['providerReasons'] = $this->providerReasons($candidates);
        }

        return $outcome;
    }

    /**
     * Per-doctor block reasons, for the exceptions record.
     *
     * Ids and reason codes only, no PHI, because this is written to a table an
     * admin screen reads back months later.
     *
     * @param  ProviderCandidate[] $candidates
     * @return array<int, string[]>
     */
    private function providerReasons(array $candidates): array
    {
        $out = [];

        foreach ($candidates as $candidate) {
            $out[$candidate->providerId] = $candidate->rejectionReasons;
        }

        return $out;
    }

    /**
     * Build a candidate per doctor, with eligibility and live workload.
     *
     * All counts are gathered in grouped queries up front rather than per doctor,
     * because the obvious per-doctor version is a query per clinician per case
     * created, which is exactly the kind of thing that is fine in staging and
     * melts under real volume.
     *
     * @return ProviderCandidate[]
     */
    public function candidates(PatientCase $case, RoutingPolicy $policy, ?CaseRequirements $requirements = null): array
    {
        $requirements ??= CaseRequirements::fromCase($case);

        // Eager loaded because acceptsCategory() reads the relation per doctor,
        // and a lazy load here is one query per clinician per case created.
        $clinicians = Clinician::with('acceptedCategories')->get();

        if ($clinicians->isEmpty()) {
            return [];
        }

        $ids             = $clinicians->pluck('id')->all();
        $openByTriage    = $this->openCaseCounts($ids);
        $dailyVolume     = $this->dailyVolumeCounts($ids);
        $newCaseVolume   = $this->dailyNewCaseVolumeCounts($ids);
        $messageFacts    = $this->messageFacts($ids);
        $medianDecisions = $this->medianDecisionMinutes($ids);
        $providerWeights = $policy->providerWeights();
        $agingThreshold  = $policy->messageAgingThresholdHours();

        // Is the case being routed a first visit? A check-in must never be held
        // back by a new-case control, so this flag switches all of them off for
        // a refill. See EligibilityEvaluator's new-case block.
        $isNewCase = $requirements->isNewCase;

        /*
         * The two admin-set new-case criteria are computed ONLY when the policy
         * configures them and the case is a new one. Both are joins over the
         * whole case and message tables, so gathering them for a check-in, or
         * when nobody set a threshold, would be work with no consumer.
         */
        $delayedAfterHours = $policy->delayedAfterHours();
        $maxDelayedCases   = $policy->maxDelayedCases();
        $maxAwaitingReply  = $policy->maxAwaitingReply();

        $delayedCounts = ($isNewCase && $maxDelayedCases !== null && $delayedAfterHours !== null)
            ? $this->delayedCaseCounts($ids, $delayedAfterHours)
            : [];

        $awaitingReplyCounts = ($isNewCase && $maxAwaitingReply !== null)
            ? $this->awaitingReplyCounts($ids)
            : [];

        $state = $requirements->state;

        $out = [];

        foreach ($clinicians as $clinician) {
            $open = $openByTriage[$clinician->id] ?? ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0];
            $msgs = $messageFacts[$clinician->id] ?? ['unanswered' => 0, 'over12h' => 0, 'oldestHours' => null];

            // max_daily_cases of 0 or null is treated as UNCAPPED, matching how
            // MEDAXIS has always read it. Null means uncapped in the strategy, so
            // the distinction between "no cap" and "cap of zero" is preserved.
            $maxDaily = ((int) $clinician->max_daily_cases) > 0 ? (int) $clinician->max_daily_cases : null;

            // FINDING 1 FIX (Devin msg 2250). The open-case ceiling is now its
            // OWN column, not max_daily_cases reused. Before this, a doctor with
            // a full open list was permanently blocked from new work even after
            // taking nothing that day, because one number gated both a counter
            // that resets daily and one that does not.
            $maxOpen = $clinician->maxOpenCasesOrNull();

            $workload = [
                'maxDailyVolume'                  => $maxDaily,
                'currentDailyVolume'              => $dailyVolume[$clinician->id] ?? 0,
                'maxOpenCases'                    => $maxOpen,
                'currentOpenCases'                => $open['total'],
                'oldestUnansweredMessageAgeHours' => $msgs['oldestHours'],

                // New-case-only facts. Ignored by EligibilityEvaluator for a
                // check-in, since isNewCase gates the whole block.
                'isNewCase'            => $isNewCase,
                'acceptingNewCases'    => (bool) $clinician->accepting_new_cases,
                'maxDailyNewCases'     => $clinician->maxDailyNewCasesOrNull(),
                'currentDailyNewCases' => $newCaseVolume[$clinician->id] ?? 0,
                'maxDelayedCases'      => $maxDelayedCases,
                'currentDelayedCases'  => $delayedCounts[$clinician->id] ?? 0,
                'maxAwaitingReply'     => $maxAwaitingReply,
                'currentAwaitingReply' => $awaitingReplyCounts[$clinician->id] ?? 0,
            ];

            $reasons = EligibilityEvaluator::evaluate(
                $clinician,
                $state,
                $workload,
                $agingThreshold,
                $policy->requireRecordedLicensure(),
                $requirements,
            );

            $weight = $providerWeights[(string) $clinician->id]
                ?? $providerWeights[$clinician->id]
                ?? 1;

            $out[] = new ProviderCandidate(
                providerId:            $clinician->id,
                eligible:              $reasons === [],
                rejectionReasons:      $reasons,
                openGreenCases:        $open['green'],
                openYellowCases:       $open['yellow'],
                openRedCases:          $open['red'],
                unansweredMessages:    $msgs['unanswered'],
                messagesOver12Hours:   $msgs['over12h'],
                medianDecisionMinutes: $medianDecisions[$clinician->id] ?? 0.0,
                currentDailyVolume:    $dailyVolume[$clinician->id] ?? 0,
                maxDailyVolume:        $maxDaily,
                maxOpenCases:          $maxOpen,
                openCases:             $open['total'],
                weightAllocation:      is_numeric($weight) ? (float) $weight : 1.0,
                priority:              (int) $clinician->priority,
            );
        }

        return $out;
    }

    /** Open case counts per doctor, split by triage. */
    private function openCaseCounts(array $ids): array
    {
        $rows = PatientCase::selectRaw('clinician_id, triage, COUNT(*) as c')
            ->whereIn('clinician_id', $ids)
            ->whereIn('status', self::OPEN_STATUSES)
            ->groupBy('clinician_id', 'triage')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $id = $row->clinician_id;
            $out[$id] ??= ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0];
            $count = (int) $row->c;

            if (isset($out[$id][$row->triage])) {
                $out[$id][$row->triage] += $count;
            }

            $out[$id]['total'] += $count;
        }

        return $out;
    }

    /**
     * Cases assigned to each doctor today.
     *
     * Measured from `assigned_at`, not `created_at`: the cap is about how much a
     * doctor has been GIVEN today, and a case created yesterday and assigned this
     * morning is today's work.
     */
    private function dailyVolumeCounts(array $ids): array
    {
        return PatientCase::selectRaw('clinician_id, COUNT(*) as c')
            ->whereIn('clinician_id', $ids)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '>=', now()->startOfDay())
            ->groupBy('clinician_id')
            ->pluck('c', 'clinician_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * NEW cases (not check-ins) assigned to each doctor today.
     *
     * Same shape as dailyVolumeCounts but filtered to `is_refill = false`, so the
     * per-doctor new-case cap counts first visits only. A doctor's own returning
     * patients never eat into their new-case allowance.
     */
    private function dailyNewCaseVolumeCounts(array $ids): array
    {
        return PatientCase::selectRaw('clinician_id, COUNT(*) as c')
            ->whereIn('clinician_id', $ids)
            ->where('is_refill', false)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '>=', now()->startOfDay())
            ->groupBy('clinician_id')
            ->pluck('c', 'clinician_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * Cases per doctor that have sat too long without reaching a terminal state.
     *
     * "Delayed" is measured on how long the case has been in the queue, from
     * assigned_at (falling back to created_at), NOT on a message flag. That makes
     * it un-gameable: a doctor cannot clear a delayed case by opening it, only by
     * moving it forward. This is the robust half of Devin's "delayed or pending"
     * (msg 2248); the message half is awaitingReplyCounts below.
     */
    private function delayedCaseCounts(array $ids, float $delayedAfterHours): array
    {
        $cutoff = now()->subMinutes((int) round($delayedAfterHours * 60));

        return PatientCase::selectRaw('clinician_id, COUNT(*) as c')
            ->whereIn('clinician_id', $ids)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereRaw('COALESCE(assigned_at, created_at) <= ?', [$cutoff])
            ->groupBy('clinician_id')
            ->pluck('c', 'clinician_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * Open cases per doctor where the patient is waiting on a REPLY.
     *
     * A case counts when its newest INBOUND message is newer than its newest
     * OUTBOUND one (or there is an inbound and no outbound at all). This is the
     * deliberate answer to the is_read problem: opening a case marks its messages
     * read but does NOT add an outbound message, so an opened-but-unanswered case
     * still shows here. It measures whether the patient got a reply, which is what
     * "pending messages" should mean.
     */
    private function awaitingReplyCounts(array $ids): array
    {
        $rows = PatientCase::selectRaw('cases.clinician_id as cid, COUNT(*) as c')
            ->whereIn('cases.clinician_id', $ids)
            ->whereIn('cases.status', self::OPEN_STATUSES)
            ->whereRaw(
                '(SELECT MAX(m.created_at) FROM messages m
                    WHERE m.case_id = cases.id AND m.direction = ?)
                 > COALESCE((SELECT MAX(m2.created_at) FROM messages m2
                    WHERE m2.case_id = cases.id AND m2.direction = ?), ?)',
                ['inbound', 'outbound', '1970-01-01 00:00:00']
            )
            ->groupBy('cases.clinician_id')
            ->pluck('c', 'cid');

        return $rows->map(fn ($c) => (int) $c)->all();
    }

    /**
     * Unanswered inbound messages per doctor, how many are over 12 hours old, and
     * the age of the oldest.
     *
     * "Unanswered" is read as an unread inbound message on a case assigned to that
     * doctor. MEDAXIS tracks `is_read`, so this is real rather than the zero MA
     * has to use.
     */
    private function messageFacts(array $ids): array
    {
        $rows = Message::selectRaw(
                'cases.clinician_id as cid,
                 COUNT(*) as unanswered,
                 SUM(CASE WHEN messages.created_at <= ? THEN 1 ELSE 0 END) as over12h,
                 MIN(messages.created_at) as oldest',
                [now()->subHours(12)]
            )
            ->join('cases', 'cases.id', '=', 'messages.case_id')
            ->whereIn('cases.clinician_id', $ids)
            ->where('messages.direction', 'inbound')
            ->where('messages.is_read', false)
            ->groupBy('cases.clinician_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->cid] = [
                'unanswered'  => (int) $row->unanswered,
                'over12h'     => (int) $row->over12h,
                'oldestHours' => $row->oldest ? now()->diffInMinutes($row->oldest) / 60 : null,
            ];
        }

        return $out;
    }

    /** Median decision time per doctor, approximated by the average in minutes. */
    private function medianDecisionMinutes(array $ids): array
    {
        return PatientCase::selectRaw('clinician_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) as m')
            ->whereIn('clinician_id', $ids)
            ->whereNotNull('approved_at')
            ->groupBy('clinician_id')
            ->pluck('m', 'clinician_id')
            ->map(fn ($m) => (float) $m)
            ->all();
    }

    /**
     * The round-robin cursor: the doctor most recently assigned a case.
     *
     * Derived from assignment history rather than a stored cursor column, as MA
     * does, so there is no counter to drift out of sync with reality.
     */
    private function roundRobinCursor(): ?int
    {
        $id = PatientCase::whereNotNull('clinician_id')
            ->whereNotNull('assigned_at')
            ->orderByDesc('assigned_at')
            ->value('clinician_id');

        return $id ? (int) $id : null;
    }
}
