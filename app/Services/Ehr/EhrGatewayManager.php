<?php

namespace App\Services\Ehr;

use RuntimeException;

/**
 * Resolves the configured EHR gateway adapter and enforces the two-flag safety
 * gate before any real (non-mock) adapter can be used.
 *
 * Same shape as PharmacyGatewayManager on purpose.
 */
class EhrGatewayManager
{
    /** Adapters that perform no network effect and are always safe to run. */
    private const SAFE_ADAPTERS = ['mock'];

    public function resolve(): EhrGatewayAdapter
    {
        $key = config('ehr.adapter', 'mock');

        if (! in_array($key, self::SAFE_ADAPTERS, true)) {
            if (! config('ehr.enabled') || ! config('ehr.sandbox_validated')) {
                throw new RuntimeException(
                    "EHR adapter [{$key}] requires ehr.enabled AND ehr.sandbox_validated. "
                    . 'Falling back is not automatic — set both flags deliberately or use the mock adapter.'
                );
            }
        }

        return match ($key) {
            'mock'     => new MockEhrAdapter(),
            'healthie' => new HealthieEhrAdapter(),
            default    => throw new RuntimeException("Unknown EHR adapter [{$key}]."),
        };
    }

    /** True when a record should actually be pushed (vs recorded as disabled/preview). */
    public function pushEnabled(): bool
    {
        return (bool) config('ehr.enabled') && (bool) config('ehr.sandbox_validated');
    }
}
