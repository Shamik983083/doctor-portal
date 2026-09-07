<?php

namespace App\Jobs;

use App\Models\Clinician;
use App\Models\PartnerEhrSetting;
use App\Models\SubStorefront;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fans out ProvisionClinicianInHealthieJob for every eligible clinician when a
 * new sub-storefront is created with a Healthie user group.
 *
 * In the User Groups model, "provisioning" means ensuring each clinician has an
 * account in the PARTNER'S single Healthie org — not a per-sub-storefront account.
 * Sub-storefront group membership is handled at push time (ensureInGroup / care team).
 *
 * The job dispatches regardless of whether the sub-storefront itself is "pushable"
 * because clinician org membership is a precondition for push, not a consequence.
 * Each individual ProvisionClinicianInHealthieJob is idempotent and will skip
 * clinicians who are already synced.
 */
class ProvisionAllCliniciansForSubStorefrontJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $subStorefrontId) {}

    public function handle(): void
    {
        $subStorefront = SubStorefront::find($this->subStorefrontId);

        if (! $subStorefront) {
            Log::warning('ProvisionAllCliniciansForSubStorefront: sub-storefront not found', [
                'sub_storefront_id' => $this->subStorefrontId,
            ]);
            return;
        }

        // Gate on the PARTNER having Healthie credentials — individual jobs handle
        // per-clinician idempotency and will skip already-synced mappings.
        $hasPartnerSettings = PartnerEhrSetting::where('partner_id', $subStorefront->partner_id)
            ->where('provider', 'healthie')
            ->whereNotNull('organization_id')
            ->whereNotNull('api_key')
            ->whereNotNull('endpoint')
            ->exists();

        if (! $hasPartnerSettings) {
            Log::info('ProvisionAllCliniciansForSubStorefront: parent partner has no Healthie org, skipping', [
                'sub_storefront_id' => $this->subStorefrontId,
                'partner_id'        => $subStorefront->partner_id,
            ]);
            return;
        }

        // Provision global clinicians AND those explicitly assigned to this sub-storefront.
        $clinicians = Clinician::where('status', 'active')
            ->where(function ($q) use ($subStorefront) {
                $q->where('is_global', true)
                  ->orWhereHas('subStorefronts', fn ($sq) =>
                      $sq->where('sub_storefronts.id', $subStorefront->id)
                  );
            })
            ->get();

        foreach ($clinicians as $clinician) {
            ProvisionClinicianInHealthieJob::dispatch($clinician)->onQueue('default');
        }

        Log::info('ProvisionAllCliniciansForSubStorefront: dispatched individual jobs', [
            'sub_storefront_id' => $this->subStorefrontId,
            'partner_id'        => $subStorefront->partner_id,
            'clinician_count'   => $clinicians->count(),
        ]);
    }
}
