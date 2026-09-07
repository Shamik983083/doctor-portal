<?php

namespace App\Jobs;

use App\Models\Clinician;
use App\Models\ClinicianHealthieMapping;
use App\Models\PartnerEhrSetting;
use App\Services\Ehr\HealthieProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a single clinician into every partner Healthie org they should
 * belong to, creating a partner-level ClinicianHealthieMapping row per org.
 *
 * In the User Groups model there is ONE Healthie org per partner. Sub-storefronts
 * use groups within that org, so clinicians do NOT need separate accounts per
 * sub-storefront. Only the partner-level mapping is required — that ID is used
 * at push time to set dietitian_id and to add the clinician to the patient's
 * care team.
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
        // Provision into every partner org that has a Healthie API key,
        // endpoint, and organization_id configured.
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
    }

    private function provisionForPartner(HealthieProvisioningService $service, PartnerEhrSetting $settings): void
    {
        $mapping = ClinicianHealthieMapping::firstOrNew([
            'clinician_id'      => $this->clinician->id,
            'partner_id'        => $settings->partner_id,
            'sub_storefront_id' => null,
        ]);

        $alreadySynced = $mapping->exists
            && $mapping->status === ClinicianHealthieMapping::STATUS_SYNCED
            && ! empty($mapping->healthie_user_id);

        try {
            if ($alreadySynced) {
                // Account already exists — skip creation, just push updated details.
                $healthieUserId = $mapping->healthie_user_id;
            } else {
                $healthieUserId = $service->provisionClinician($this->clinician, $settings);

                $mapping->fill([
                    'healthie_user_id' => $healthieUserId,
                    'healthie_org_id'  => $settings->organization_id,
                    'status'           => ClinicianHealthieMapping::STATUS_SYNCED,
                    'last_error'       => null,
                    'synced_at'        => now(),
                ])->save();

                Log::info('HealthieProvisioning: clinician provisioned (partner-level)', [
                    'clinician_id'     => $this->clinician->id,
                    'partner_id'       => $settings->partner_id,
                    'healthie_user_id' => $healthieUserId,
                ]);
            }

            // Always push current NPI, credentials, specialty, licensed states
            // regardless of whether the account was newly created or already existed.
            // This is the update path for profile changes.
            $service->updateProviderDetails($this->clinician, $settings, $healthieUserId);

            // Stamp synced_at on updates too so admin can see the last sync time.
            $mapping->fill(['synced_at' => now(), 'last_error' => null])->save();

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
}
