<?php

namespace App\Services\Routing;

/**
 * The two kinds of visit (Devin msg 2313 Q4: "the Sync is determined by states").
 *
 * ASYNCHRONOUS is store-and-forward: the patient submits, the doctor reviews and
 * decides in their own time. Every case in MEDAXIS today is one of these.
 *
 * SYNCHRONOUS is a live video visit, required by some states for some products.
 * It needs a doctor who takes them AND a booking link the patient can use, which
 * is why `Clinician::acceptsVisitType()` checks both.
 *
 * Deliberately NOT derived from `cases.visit_type`. That column is free text set
 * by partners and is already load-bearing for check-in detection; reading a legal
 * requirement out of a partner-supplied string would let a storefront decide
 * whether its own patients need a video visit. The requirement comes from the
 * state matrix instead.
 */
final class VisitType
{
    public const ASYNCHRONOUS = 'ASYNCHRONOUS';
    public const SYNCHRONOUS  = 'SYNCHRONOUS';

    public const LABELS = [
        self::ASYNCHRONOUS => 'Asynchronous review',
        self::SYNCHRONOUS  => 'Synchronous video visit',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }
}
