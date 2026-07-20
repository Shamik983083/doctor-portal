<?php

namespace App\Services\Routing;

/**
 * The routing modes, ported from MA-DOCPORTAL (`packages/domain/src/routing.ts`).
 *
 * MA's four modes are carried over exactly. PRIORITY is MEDAXIS's own existing
 * behaviour, kept as a fifth mode and as the DEFAULT so that porting MA's routing
 * ADDS options without changing who gets which case until someone deliberately
 * activates a different policy. Devin msg 2115: add what MA has, remove nothing.
 */
final class RoutingMode
{
    /** Strict rotation among eligible providers, deterministic given the cursor. */
    public const ROUND_ROBIN = 'ROUND_ROBIN';

    /** Per-provider weights; the provider furthest below its allocation share wins. */
    public const WEIGHTED = 'WEIGHTED';

    /** Configurable weighted-workload score; lowest score wins. */
    public const INTELLIGENT = 'INTELLIGENT';

    /** No push assignment; the case waits in a pool to be claimed. */
    public const PROVIDER_POOL = 'PROVIDER_POOL';

    /** MEDAXIS's existing behaviour: lowest `priority` rank with capacity. */
    public const PRIORITY = 'PRIORITY';

    public const ALL = [
        self::PRIORITY,
        self::ROUND_ROBIN,
        self::WEIGHTED,
        self::INTELLIGENT,
        self::PROVIDER_POOL,
    ];

    public const LABELS = [
        self::PRIORITY      => 'Priority order (MEDAXIS default)',
        self::ROUND_ROBIN   => 'Round robin',
        self::WEIGHTED      => 'Weighted allocation',
        self::INTELLIGENT   => 'Intelligent workload score',
        self::PROVIDER_POOL => 'Provider pool (no auto-assignment)',
    ];

    public const DESCRIPTIONS = [
        self::PRIORITY      => 'Assigns to the lowest priority rank with capacity. This is how MEDAXIS has always behaved.',
        self::ROUND_ROBIN   => 'Rotates strictly through eligible doctors so everyone gets an even turn.',
        self::WEIGHTED      => 'Distributes by each doctor\'s configured share. A weight of 0 takes that doctor out of routing.',
        self::INTELLIGENT   => 'Scores each doctor on open cases by triage, unanswered messages, decision speed and volume. Lowest score wins.',
        self::PROVIDER_POOL => 'Nothing is auto-assigned. Cases wait for a doctor to pick them up.',
    ];
}
