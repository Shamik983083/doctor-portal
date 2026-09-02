<?php

namespace App\Jobs;

use App\Models\Clinician;
use App\Models\ClinicianHealthieMapping;
use App\Models\PartnerEhrSetting;
use App\Models\SubStorefront;
use App\Services\Ehr\HealthieProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a single clinician into all Healthie sub-orgs they should belong to.
 *
 * Global clinicians: every partner that has a Healthie API key + organization_id.
 * Non-global clinicians: same for now — scoping will tighten once the
 * compliance/rxos-laws branch (clinician-partner pivot) is merged.
 *
 * Idempotent: rows already in STATUS_SYNCED with a user ID are skipped.
 * Failed rows are retried (up to $tries) with exponential backoff.
 */
class ProvisionClinicianInHealthieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(public Clinician $clinician) {}

    public function handle(HealthieProvisioningService $service): void
    {
        // Partner-level provisioning (existing partners without sub-storefronts)
        $settings = PartnerEhrSetting::where('provider', 'healthie')
            ->whereNotNull('organization_id')
            ->where('organization_id', '!=', '')
            ->get();

        foreach ($settings as $setting) {
            if (empty($setting->api_key) || empty($setting->endpoint)) {
                continue;
            }

            $this->provisionForPartner($service, $setting);
        }

        // Sub-storefront-level provisioning — each sub-storefront is its own Healthie sub-org
        $subStorefronts = SubStorefront::whereNotNull('healthie_organization_id')
            ->where('healthie_organization_id', '!=', '')
            ->where('healthie_is_enabled', true)
            ->get();

        foreach ($subStorefronts as $subStorefront) {
            if (empty($subStorefront->healthie_api_key) || empty($subStorefront->healthie_endpoint)) {
                continue;
            }

            $this->provisionForSubStorefront($service, $subStorefront);
        }
    }

    private function provisionForPartner(HealthieProvisioningService $service, PartnerEhrSetting $settings): void
    {
        // Partner-level mapping: sub_storefront_id is null
        $mapping = ClinicianHealthieMapping::firstOrNew([
            'clinician_id'      => $this->clinician->id,
            'partner_id'        => $settings->partner_id,
            'sub_storefront_id' => null,
        ]);

        if ($mapping->exists
            && $mapping->status === ClinicianHealthieMapping::STATUS_SYNCED
            && ! empty($mapping->healthie_user_id)) {
            return;
        }

        try {
            $healthieUserId = $service->provisionClinician($this->clinician, $settings);

            $mapping->fill([
                'healthie_user_id' => $healthieUserId,
                'healthie_org_id'  => $settings->organization_id,
                'status'           => ClinicianHealthieMapping::STATUS_SYNCED,
                'last_error'       => null,
                'synced_at'        => now(),
            ])->save();

            Log::info('HealthieProvisioning: clinician synced (partner-level)', [
                'clinician_id'     => $this->clinician->id,
                'partner_id'       => $settings->partner_id,
                'healthie_user_id' => $healthieUserId,
            ]);
        } catch (\Throwable $e) {
            $mapping->fill([
                'status'     => ClinicianHealthieMapping::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            Log::warning('HealthieProvisioning: clinician sync failed (partner-level)', [
                'clinician_id' => $this->clinician->id,
                'partner_id'   => $settings->partner_id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    private function provisionForSubStorefront(HealthieProvisioningService $service, SubStorefront $subStorefront): void
    {
        // Sub-storefront mapping: keyed on (clinician_id, sub_storefront_id)
        $mapping = ClinicianHealthieMapping::firstOrNew([
            'clinician_id'      => $this->clinician->id,
            'partner_id'        => $subStorefront->partner_id,
            'sub_storefront_id' => $subStorefront->id,
        ]);

        if ($mapping->exists
            && $mapping->status === ClinicianHealthieMapping::STATUS_SYNCED
            && ! empty($mapping->healthie_user_id)) {
            return;
        }

        try {
            $healthieUserId = $service->provisionClinicianInSubStorefront($this->clinician, $subStorefront);

            $mapping->fill([
                'healthie_user_id' => $healthieUserId,
                'healthie_org_id'  => $subStorefront->healthie_organization_id,
                'status'           => ClinicianHealthieMapping::STATUS_SYNCED,
                'last_error'       => null,
                'synced_at'        => now(),
            ])->save();

            Log::info('HealthieProvisioning: clinician synced (sub-storefront)', [
                'clinician_id'      => $this->clinician->id,
                'sub_storefront_id' => $subStorefront->id,
                'partner_id'        => $subStorefront->partner_id,
                'healthie_user_id'  => $healthieUserId,
            ]);
        } catch (\Throwable $e) {
            $mapping->fill([
                'status'     => ClinicianHealthieMapping::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            Log::warning('HealthieProvisioning: clinician sync failed (sub-storefront)', [
                'clinician_id'      => $this->clinician->id,
                'sub_storefront_id' => $subStorefront->id,
                'partner_id'        => $subStorefront->partner_id,
                'error'             => $e->getMessage(),
            ]);
        }
    }
}
