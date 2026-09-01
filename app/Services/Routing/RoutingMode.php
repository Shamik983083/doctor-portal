<?php

namespace App\Services\Routing;

/**
 * The routing modes, ported from MA-DOCPORTAL (`packages/domain/src/routing.ts`).
 *
 * ── INTELLIGENT WAS RETIRED AS A MODE (Devin msg 2313 Q5) ────────────────────
 *
 * "adjust it for pool eligibility. I think there was confusion on the initial
 * build but that was meant to be the logic behind the pool."
 *
 * Its eight coefficients were never meant to pick a doctor for a case. They were
 * meant to decide whether a doctor is in good enough standing to PULL work out of
 * the pool, which is where they now live: see PoolEligibilityEvaluator and
 * RoutingPolicy::poolCriteria(). The constant and the strategy branch survive so
 * that a stored policy naming INTELLIGENT keeps routing instead of fail-closing
 * to NONE, but it is no longer offered on the admin screen.
 *
 * ── THE FOUR SELECTABLE MODES (Devin msg 2308) ───────────────────────────────
 *
 * PRIORITY       saturate then spill: fill the top-ranked doctor to their cap
 *                before the next one takes anything.
 * ROUND_ROBIN    even spread by strict rotation. Devin msg 2313 Q1: "okay keep
 *                the rotations". His breakout said "randomly", and the rotation
 *                was kept deliberately, because a rotation can reproduce its own
 *                answer months later and a random pick cannot. Reproducibility is
 *                the reason policies are versioned at all.
 * WEIGHTED       split by admin-set share; weight 0 excludes entirely.
 * PROVIDER_POOL  nothing is pushed. Doctors request work and the pool grants it,
 *                oldest case first. See PoolPullService.
 *
 * A policy carries one mode per PATH (new cases, check-ins), not one overall.
 * See RoutingPolicy::modeForCase().
 */
final class RoutingMode
{
    /** Strict rotation among eligible providers, deterministic given the cursor. */
    public const ROUND_ROBIN = 'ROUND_ROBIN';

    /** Per-provider weights; the provider furthest below its allocation share wins. */
    public const WEIGHTED = 'WEIGHTED';

    /**
     * RETIRED as a selectable mode (Devin msg 2313 Q5). Kept so stored policies
     * naming it still route. Its coefficients now drive pool eligibility.
     */
    public const INTELLIGENT = 'INTELLIGENT';

    /** No push assignment. Doctors pull from the pool; the pool decides. */
    public const PROVIDER_POOL = 'PROVIDER_POOL';

    /** MEDAXIS's existing behaviour: lowest `priority` rank with capacity. */
    public const PRIORITY = 'PRIORITY';

    /** Offered on the admin screen. */
    public const ALL = [
        self::PRIORITY,
        self::ROUND_ROBIN,
        self::WEIGHTED,
        self::PROVIDER_POOL,
    ];

    /**
     * Every mode the strategy can still execute, including the retired one.
     *
     * Used to tell "no doctor was eligible" apart from "this policy names a mode
     * that does not exist". Both assign nobody; only the second means routing is
     * broken for every case in the system.
     */
    public const ALL_STORED = [
        self::PRIORITY,
        self::ROUND_ROBIN,
        self::WEIGHTED,
        self::INTELLIGENT,
        self::PROVIDER_POOL,
    ];

    public const LABELS = [
        self::PRIORITY      => 'Priority order (fill to volume first)',
        self::ROUND_ROBIN   => 'Round robin (even spread)',
        self::WEIGHTED      => 'Weighted allocation',
        self::INTELLIGENT   => 'Intelligent workload score (retired)',
        self::PROVIDER_POOL => 'Provider pool (doctors request cases)',
    ];

    public const DESCRIPTIONS = [
        self::PRIORITY      => 'Ranks doctors and fills the top-ranked one to their cap before the next takes anything.',
        self::ROUND_ROBIN   => 'Rotates through eligible doctors so the group gets an even spread. Deterministic, so any past assignment can be explained.',
        self::WEIGHTED      => 'Distributes by each doctor\'s configured share. A weight of 0 takes that doctor out of routing.',
        self::INTELLIGENT   => 'Retired as a mode. These coefficients now decide who may pull from the provider pool.',
        self::PROVIDER_POOL => 'Nothing is auto-assigned. A doctor requests a number of cases and the pool grants the oldest ones they are eligible for.',
    ];
}
