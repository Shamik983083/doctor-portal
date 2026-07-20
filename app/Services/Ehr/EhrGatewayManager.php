<?php

namespace App\Services\Ehr;

use App\Models\PartnerEhrSetting;
use RuntimeException;

/**
 * Resolves the EHR gateway adapter FOR A GIVEN COMPANY and enforces the two-flag
 * safety gate before any real (non-mock) adapter can be used.
 *
 * Deliberately company-scoped rather than global. A global resolve() would hand
 * back one credential for every storefront, which is the single change most
 * likely to cross tenant data, so the signature makes it impossible: you cannot
 * get a real adapter without saying which company it is for.
 */
class EhrGatewayManager
{
    /** Adapters that perform no network effect and are always safe to run. */
    private const SAFE_ADAPTERS = ['mock'];

    public function resolve(?int $partnerId = null): EhrGatewayAdapter
    {
        $key = config('ehr.adapter', 'mock');

        if (in_array($key, self::SAFE_ADAPTERS, true)) {
            return new MockEhrAdapter();
        }

        // Global gates first: the platform must be switched on at all.
        if (! config('ehr.enabled') || ! config('ehr.sandbox_validated')) {
            throw new RuntimeException(
                "EHR adapter [{$key}] requires ehr.enabled AND ehr.sandbox_validated. "
                . 'Falling back is not automatic — set both flags deliberately or use the mock adapter.'
            );
        }

        if ($partnerId === null) {
            throw new RuntimeException(
                "EHR adapter [{$key}] must be resolved for a specific company. Healthie records are "
                . 'segregated per storefront and there is no shared credential.'
            );
        }

        return match ($key) {
            'healthie' => new HealthieEhrAdapter($this->settingsFor($partnerId, 'healthie')),
            default    => throw new RuntimeException("Unknown EHR adapter [{$key}]."),
        };
    }

    /**
     * This company's credentials, or a refusal.
     *
     * Never falls back to another company's settings or to a global key. An
     * unconfigured company is a configuration error to be fixed, not a reason to
     * push with somebody else's credential.
     */
    private function settingsFor(int $partnerId, string $provider): PartnerEhrSetting
    {
        $settings = PartnerEhrSetting::where('partner_id', $partnerId)
            ->where('provider', $provider)
            ->first();

        if (! $settings) {
            throw new RuntimeException(
                "No {$provider} settings are configured for partner [{$partnerId}]. Every company pushes with "
                . 'its own credential; there is no shared one. See docs/integrations/HEALTHIE-SETUP.md.'
            );
        }

        return $settings;
    }

    /**
     * True when a record should actually be pushed for this company (vs recorded
     * as disabled/preview). A company that is not fully configured is treated as
     * disabled, so it previews rather than erroring on every approval.
     */
    public function pushEnabled(?int $partnerId = null): bool
    {
        if (! config('ehr.enabled') || ! config('ehr.sandbox_validated')) {
            return false;
        }

        if (in_array(config('ehr.adapter', 'mock'), self::SAFE_ADAPTERS, true)) {
            return false;
        }

        if ($partnerId === null) {
            return false;
        }

        $settings = PartnerEhrSetting::where('partner_id', $partnerId)
            ->where('provider', config('ehr.adapter'))
            ->first();

        return $settings ? $settings->isPushable() : false;
    }
}
