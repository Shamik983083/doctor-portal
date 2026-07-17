<?php

namespace App\Services\Dispatch;

use RuntimeException;

/**
 * Resolves the configured pharmacy gateway adapter and enforces the two-flag
 * safety gate before any real (non-mock) adapter can be used.
 */
class PharmacyGatewayManager
{
    /** Adapters that perform no network effect and are always safe to run. */
    private const SAFE_ADAPTERS = ['mock'];

    public function resolve(): PharmacyGatewayAdapter
    {
        $key = config('dispatch.adapter', 'mock');

        // A non-safe adapter may only be resolved when dispatch is enabled AND
        // the operator has validated the vendor sandbox.
        if (! in_array($key, self::SAFE_ADAPTERS, true)) {
            if (! config('dispatch.enabled') || ! config('dispatch.sandbox_validated')) {
                throw new RuntimeException(
                    "Pharmacy adapter [{$key}] requires dispatch.enabled AND dispatch.sandbox_validated. "
                    . 'Falling back is not automatic — set both flags deliberately or use the mock adapter.'
                );
            }
        }

        return match ($key) {
            'mock'     => new MockPharmacyAdapter(),
            'lifefile' => new LifeFilePharmacyAdapter(),
            default    => throw new RuntimeException("Unknown pharmacy adapter [{$key}]."),
        };
    }

    /** True when a dispatch should actually be pushed (vs recorded as disabled/preview). */
    public function dispatchEnabled(): bool
    {
        return (bool) config('dispatch.enabled');
    }
}
