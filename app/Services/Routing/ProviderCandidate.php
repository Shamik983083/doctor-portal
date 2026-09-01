<?php

namespace App\Services\Routing;

/**
 * One doctor reduced to the operational facts routing reasons over.
 *
 * Ported from MA-DOCPORTAL's ProviderCandidate. No PHI: these are workload
 * counters, credentials and configuration, never patient data.
 *
 * `maxDailyVolume` and `maxOpenCases` are nullable, and null means UNCAPPED. That
 * distinction is load-bearing: 0 is a real cap meaning "take nothing", and
 * conflating it with null would either block everyone or nobody.
 */
final class ProviderCandidate
{
    public function __construct(
        public readonly int $providerId,
        public readonly bool $eligible,
        /** @var string[] Hard-block reason codes; empty when eligible. */
        public readonly array $rejectionReasons = [],
        public readonly int $openGreenCases = 0,
        public readonly int $openYellowCases = 0,
        public readonly int $openRedCases = 0,
        public readonly int $unansweredMessages = 0,
        public readonly int $messagesOver12Hours = 0,
        public readonly float $medianDecisionMinutes = 0.0,
        /** Cases assigned to this doctor so far today. */
        public readonly int $currentDailyVolume = 0,
        /** null = uncapped. */
        public readonly ?int $maxDailyVolume = null,
        /** null = uncapped. */
        public readonly ?int $maxOpenCases = null,
        /** Current open (non-terminal) case count. */
        public readonly int $openCases = 0,
        /**
         * Routing weight. In WEIGHTED mode this is the allocation share and 0
         * excludes the doctor entirely; in INTELLIGENT mode it normalizes the
         * allocation penalty.
         */
        public readonly float $weightAllocation = 1.0,
        /** MEDAXIS's own priority rank, used by PRIORITY mode. */
        public readonly int $priority = 0,
    ) {}

    public function withWeight(float $weight): self
    {
        return new self(
            $this->providerId, $this->eligible, $this->rejectionReasons,
            $this->openGreenCases, $this->openYellowCases, $this->openRedCases,
            $this->unansweredMessages, $this->messagesOver12Hours, $this->medianDecisionMinutes,
            $this->currentDailyVolume, $this->maxDailyVolume, $this->maxOpenCases,
            $this->openCases, $weight, $this->priority,
        );
    }
}
