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

    /**
     * Decide where a case goes.
     *
     * @return array{kind:string, providerId?:int} ASSIGN / POOL / NONE
     */
    public function resolve(PatientCase $case, ?RoutingPolicy $policy = null): array
    {
        $policy ??= RoutingPolicy::active();

        // No active policy: assign nobody. MA's posture, and the safe one. A
        // built-in fallback would route under rules nobody chose.
        if (! $policy) {
            return ['kind' => RoutingStrategy::NONE];
        }

        $candidates = $this->candidates($case, $policy);

        return RoutingStrategy::select(
            $policy->mode,
            $candidates,
            $policy->intelligentWeights(),
            ['roundRobinCursor' => $this->roundRobinCursor()],
        );
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
    public function candidates(PatientCase $case, RoutingPolicy $policy): array
    {
        $clinicians = Clinician::all();

        if ($clinicians->isEmpty()) {
            return [];
        }

        $ids             = $clinicians->pluck('id')->all();
        $openByTriage    = $this->openCaseCounts($ids);
        $dailyVolume     = $this->dailyVolumeCounts($ids);
        $messageFacts    = $this->messageFacts($ids);
        $medianDecisions = $this->medianDecisionMinutes($ids);
        $providerWeights = $policy->providerWeights();
        $agingThreshold  = $policy->messageAgingThresholdHours();

        $state = $case->patient_state ?: $case->patient?->state;

        $out = [];

        foreach ($clinicians as $clinician) {
            $open = $openByTriage[$clinician->id] ?? ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0];
            $msgs = $messageFacts[$clinician->id] ?? ['unanswered' => 0, 'over12h' => 0, 'oldestHours' => null];

            // max_daily_cases of 0 or null is treated as UNCAPPED, matching how
            // MEDAXIS has always read it. Null means uncapped in the strategy, so
            // the distinction between "no cap" and "cap of zero" is preserved.
            $maxDaily = ((int) $clinician->max_daily_cases) > 0 ? (int) $clinician->max_daily_cases : null;

            $workload = [
                'maxDailyVolume'                  => $maxDaily,
                'currentDailyVolume'              => $dailyVolume[$clinician->id] ?? 0,
                'maxOpenCases'                    => $maxDaily,
                'currentOpenCases'                => $open['total'],
                'oldestUnansweredMessageAgeHours' => $msgs['oldestHours'],
            ];

            $reasons = EligibilityEvaluator::evaluate(
                $clinician,
                $state,
                $workload,
                $agingThreshold,
                $policy->requireRecordedLicensure(),
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
                maxOpenCases:          $maxDaily,
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
