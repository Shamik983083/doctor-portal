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
 *   - a missing licence blocks.
 *
 * ONE DELIBERATE BEHAVIOUR CHANGE, AND IT IS THE IMPORTANT ONE.
 * MEDAXIS's old CaseAutoAssigner, when no clinician was licensed in the patient's
 * state, logged a warning and then ASSIGNED SOMEONE UNLICENSED ANYWAY so cases
 * would not stick in the queue. MA treats a missing state licence as a hard block.
 * This port follows MA: an unlicensed doctor is never selected. A case with no
 * licensed doctor now WAITS instead of being routed to someone who cannot lawfully
 * prescribe for that patient. That trades a stuck case for a compliance problem,
 * which is the right way round, but it IS a change in behaviour and is called out
 * in docs/integrations/ROUTING.md.
 */
final class EligibilityEvaluator
{
    public const PROVIDER_NOT_ACTIVE               = 'PROVIDER_NOT_ACTIVE';
    public const PROVIDER_UNAVAILABLE              = 'PROVIDER_UNAVAILABLE';
    public const RESIDENCE_STATE_LICENSE_MISSING   = 'RESIDENCE_STATE_LICENSE_MISSING';
    public const LICENSURE_NOT_RECORDED            = 'LICENSURE_NOT_RECORDED';
    public const DAILY_VOLUME_CAP_REACHED          = 'DAILY_VOLUME_CAP_REACHED';
    public const OPEN_CASES_CAP_REACHED            = 'OPEN_CASES_CAP_REACHED';
    public const MESSAGE_AGING_BLOCK               = 'MESSAGE_AGING_BLOCK';

    public const REASON_LABELS = [
        self::PROVIDER_NOT_ACTIVE             => 'Doctor is not active',
        self::PROVIDER_UNAVAILABLE            => 'Doctor is marked unavailable',
        self::RESIDENCE_STATE_LICENSE_MISSING => 'No licence in the patient\'s state',
        self::LICENSURE_NOT_RECORDED          => 'No licensed states recorded for this doctor',
        self::DAILY_VOLUME_CAP_REACHED        => 'Daily case cap reached',
        self::OPEN_CASES_CAP_REACHED          => 'Open case cap reached',
        self::MESSAGE_AGING_BLOCK             => 'Has a message older than the configured limit',
    ];

    /**
     * @param  array{currentDailyVolume:int,currentOpenCases:int,maxDailyVolume:?int,maxOpenCases:?int,oldestUnansweredMessageAgeHours:?float} $workload
     * @return string[] every reason that fired, in a fixed order
     */
    public static function evaluate(
        Clinician $clinician,
        ?string $residenceState,
        array $workload,
        ?float $messageAgingThresholdHours = null,
        bool $requireRecordedLicensure = false,
    ): array {
        $reasons = [];

        if ($clinician->status !== 'active') {
            $reasons[] = self::PROVIDER_NOT_ACTIVE;
        }

        if (! $clinician->is_available) {
            $reasons[] = self::PROVIDER_UNAVAILABLE;
        }

        /*
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
             * WATCH THIS ONE. `Clinician::isLicensedInState()` returns TRUE when a
             * doctor has NO licensed states recorded: an empty list is treated as
             * "licensed everywhere". That is fail-OPEN, and it silently makes the
             * licence hard block vacuous for every doctor whose licence data was
             * never filled in, which is the population most likely to be wrong.
             *
             * That shared helper is used elsewhere, so it is not quietly changed
             * here. Instead the gap is made VISIBLE and closable:
             *
             *   $requireRecordedLicensure = false (default) keeps today's
             *     behaviour, so activating a routing policy does not suddenly
             *     block every doctor with blank licence data.
             *   $requireRecordedLicensure = true treats blank licensure as a
             *     block, which is the compliance-grade reading.
             *
             * Set it once licensed states are actually populated. See
             * docs/integrations/ROUTING.md section 2.3.
             */
            $hasRecordedLicensure = ! empty($clinician->licensed_states);

            if (! $hasRecordedLicensure) {
                if ($requireRecordedLicensure) {
                    $reasons[] = self::LICENSURE_NOT_RECORDED;
                }
                // else: falls through as licensed, matching current behaviour.
            } elseif (! $clinician->isLicensedInState($state)) {
                $reasons[] = self::RESIDENCE_STATE_LICENSE_MISSING;
            }
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
