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
        $settings = PartnerEhrSetting::where('provider', 'healthie')
            ->whereNotNull('organization_id')
            ->where('organization_id', '!=', '')
            ->get();

        foreach ($settings as $setting) {
            // Skip partners with no API key — they can't accept provisioning calls.
            if (empty($setting->api_key) || empty($setting->endpoint)) {
                continue;
            }

            $this->provisionForPartner($service, $setting);
        }
    }

    private function provisionForPartner(HealthieProvisioningService $service, PartnerEhrSetting $settings): void
    {
        $mapping = ClinicianHealthieMapping::firstOrNew([
            'clinician_id' => $this->clinician->id,
            'partner_id'   => $settings->partner_id,
        ]);

        // Already synced with a valid Healthie user ID — nothing to do.
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

            Log::info('HealthieProvisioning: clinician synced', [
                'clinician_id'     => $this->clinician->id,
                'partner_id'       => $settings->partner_id,
                'healthie_user_id' => $healthieUserId,
            ]);
        } catch (\Throwable $e) {
            $mapping->fill([
                'status'     => ClinicianHealthieMapping::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            Log::warning('HealthieProvisioning: clinician sync failed', [
                'clinician_id' => $this->clinician->id,
                'partner_id'   => $settings->partner_id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
