<?php

namespace App\Services\Routing;

/**
 * The answer to "may this doctor pull work right now?".
 *
 * Three outcomes, not two, and the third is the one Devin specified in msg 2313
 * Q6 ("it should bypass, or seek approval from Dr Admin"):
 *
 *   allowed                 grant the pull
 *   blocked                 refuse it outright, and say which criterion closed
 *   requiresApproval        hold it for a Doctor Admin to decide
 *
 * The split matters because the two kinds of limit come from different people.
 * The POOL CRITERIA are set by whoever owns the routing policy and are a hard
 * refusal. The SLA is set by the doctor's own admin, and that admin chose whether
 * breaching it stops the doctor or merely asks them.
 */
final class PoolEligibility
{
    public function __construct(
        /** @var string[] Hard refusals from the policy's pool criteria. */
        public readonly array $blockingReasons = [],
        /** @var string[] SLA breaches, which may or may not block. */
        public readonly array $slaViolations = [],
        public readonly bool $requiresApproval = false,
    ) {}

    public function isBlocked(): bool
    {
        return $this->blockingReasons !== [];
    }

    public function isAllowed(): bool
    {
        return ! $this->isBlocked() && ! $this->requiresApproval;
    }

    /** Everything that fired, for the record on the pull request. */
    public function allReasons(): array
    {
        return array_values(array_unique(array_merge($this->blockingReasons, $this->slaViolations)));
    }
}
