<?php

namespace App\Services\Routing;

/**
 * The routing decision: pure, no I/O, no database.
 *
 * Ported from MA-DOCPORTAL `packages/domain/src/routing.ts`, rule for rule.
 *
 * THE PROPERTIES THAT MATTER, all carried over deliberately:
 *
 *  - TOTAL AND DETERMINISTIC. Identical inputs always give the identical answer,
 *    with providerId as the final tie-break everywhere, so there is never an
 *    order-dependent or random pick. Two runs over the same data agree.
 *  - HARD BLOCKS ARE NEVER SCORED. A blocked doctor cannot be selected by any
 *    mode, whatever their score would have been.
 *  - FAIL CLOSED. An unknown mode assigns NOBODY rather than falling through to a
 *    default. A malformed stored policy leaves cases in the queue, which is
 *    recoverable; silently routing under the wrong rules is not.
 *  - RANKING ONLY. This chooses; it does not write. The caller re-validates before
 *    assigning, so this can never authorize something the write path would refuse.
 */
final class RoutingStrategy
{
    public const ASSIGN = 'ASSIGN';
    public const POOL   = 'POOL';
    public const NONE   = 'NONE';

    /**
     * Whether a candidate is hard-blocked: ineligible, carrying any rejection
     * reason, or at/over a configured cap.
     *
     * Note `>=` on both caps: at the cap means full, not "one more is fine".
     */
    public static function isHardBlocked(ProviderCandidate $c): bool
    {
        return ! $c->eligible
            || count($c->rejectionReasons) > 0
            || ($c->maxDailyVolume !== null && $c->currentDailyVolume >= $c->maxDailyVolume)
            || ($c->maxOpenCases !== null && $c->openCases >= $c->maxOpenCases);
    }

    /**
     * The intelligent score: a linear combination of workload signals plus an
     * allocation penalty. LOWER WINS.
     *
     * A hard-blocked candidate scores INF so it can never be chosen. A doctor with
     * no configured weight takes a deliberately heavy penalty (10x) so they do not
     * absorb volume meant for weighted peers.
     */
    public static function score(ProviderCandidate $c, RoutingWeights $w): float
    {
        if (self::isHardBlocked($c)) {
            return INF;
        }

        $workload =
            $c->openGreenCases        * $w->get('greenCase') +
            $c->openYellowCases       * $w->get('yellowCase') +
            $c->openRedCases          * $w->get('redCase') +
            $c->unansweredMessages    * $w->get('unansweredMessage') +
            $c->messagesOver12Hours   * $w->get('messageOver12HoursPenalty') +
            $c->medianDecisionMinutes * $w->get('medianDecisionMinute') +
            $c->currentDailyVolume    * $w->get('dailyVolume');

        $allocationPenalty = $c->weightAllocation > 0
            ? ($c->currentDailyVolume / $c->weightAllocation) * $w->get('allocationPenalty')
            : $w->get('allocationPenalty') * 10;

        return $workload + $allocationPenalty;
    }

    /**
     * Decide the outcome for one case.
     *
     * @param  ProviderCandidate[] $candidates
     * @param  array{roundRobinCursor?: int|null} $state
     * @return array{kind: string, providerId?: int}
     */
    public static function select(string $mode, array $candidates, RoutingWeights $weights, array $state = []): array
    {
        // PROVIDER_POOL short-circuits before eligibility: the case waits to be
        // claimed regardless of who would have been eligible.
        if ($mode === RoutingMode::PROVIDER_POOL) {
            return ['kind' => self::POOL];
        }

        $eligible = array_values(array_filter($candidates, fn ($c) => ! self::isHardBlocked($c)));

        if ($eligible === []) {
            return ['kind' => self::NONE];
        }

        return match ($mode) {
            RoutingMode::PRIORITY    => self::assignOrNone(self::selectPriority($eligible)),
            RoutingMode::ROUND_ROBIN => self::assignOrNone(self::selectRoundRobin($eligible, $state['roundRobinCursor'] ?? null)),
            RoutingMode::WEIGHTED    => self::assignOrNone(self::selectWeighted($eligible)),
            RoutingMode::INTELLIGENT => self::assignOrNone(self::selectIntelligent($eligible, $weights)),
            // Fail closed. An out-of-enum mode (a malformed persisted policy)
            // assigns nobody rather than quietly picking a default.
            default => ['kind' => self::NONE],
        };
    }

    /** MEDAXIS's existing rule: lowest priority rank, then lowest id. */
    private static function selectPriority(array $eligible): ?ProviderCandidate
    {
        usort($eligible, fn ($a, $b) => [$a->priority, $a->providerId] <=> [$b->priority, $b->providerId]);

        return $eligible[0] ?? null;
    }

    /**
     * Strict rotation: sorted ascending by providerId, take the first that sorts
     * after the cursor, wrapping to the head when the cursor is at or past the
     * tail, or is null.
     */
    private static function selectRoundRobin(array $eligible, ?int $cursor): ?ProviderCandidate
    {
        usort($eligible, fn ($a, $b) => $a->providerId <=> $b->providerId);

        if ($cursor === null) {
            return $eligible[0] ?? null;
        }

        foreach ($eligible as $c) {
            if ($c->providerId > $cursor) {
                return $c;
            }
        }

        return $eligible[0] ?? null;    // wrap
    }

    /**
     * Weighted allocation: among doctors with a positive weight, the one furthest
     * below their share, i.e. the lowest volume/weight ratio.
     *
     * A weight of 0 is excluded entirely. If that leaves nobody, this returns null
     * and the case stays unassigned rather than falling back to an unweighted pick,
     * because "excluded from weighted routing" has to mean excluded.
     */
    private static function selectWeighted(array $eligible): ?ProviderCandidate
    {
        $weighted = array_values(array_filter($eligible, fn ($c) => $c->weightAllocation > 0));

        if ($weighted === []) {
            return null;
        }

        usort($weighted, function ($a, $b) {
            $ra = $a->currentDailyVolume / $a->weightAllocation;
            $rb = $b->currentDailyVolume / $b->weightAllocation;

            return [$ra, $a->providerId] <=> [$rb, $b->providerId];
        });

        return $weighted[0];
    }

    /** Lowest score wins, INF filtered out, deterministic providerId tie-break. */
    private static function selectIntelligent(array $eligible, RoutingWeights $weights): ?ProviderCandidate
    {
        $scored = [];

        foreach ($eligible as $c) {
            $s = self::score($c, $weights);
            if (is_finite($s)) {
                $scored[] = ['c' => $c, 's' => $s];
            }
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, fn ($a, $b) => [$a['s'], $a['c']->providerId] <=> [$b['s'], $b['c']->providerId]);

        return $scored[0]['c'];
    }

    private static function assignOrNone(?ProviderCandidate $c): array
    {
        return $c === null
            ? ['kind' => self::NONE]
            : ['kind' => self::ASSIGN, 'providerId' => $c->providerId];
    }
}
