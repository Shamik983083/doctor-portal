<?php

namespace App\Jobs;

use App\Models\Clinician;
use App\Models\SubStorefront;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fans out ProvisionClinicianInHealthieJob for every active global clinician
 * when a new sub-storefront is created or updated with a Healthie org ID.
 *
 * Each individual job handles its own idempotency — already-synced mappings
 * are skipped — so this job is safe to re-dispatch without duplicate accounts.
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

        if (! $subStorefront->isPushable()) {
            Log::info('ProvisionAllCliniciansForSubStorefront: sub-storefront not pushable, skipping', [
                'sub_storefront_id' => $this->subStorefrontId,
            ]);
            return;
        }

        $clinicians = Clinician::where('is_global', true)
            ->where('status', 'active')
            ->get();

        foreach ($clinicians as $clinician) {
            ProvisionClinicianInHealthieJob::dispatch($clinician)->onQueue('default');
        }

        Log::info('ProvisionAllCliniciansForSubStorefront: dispatched individual jobs', [
            'sub_storefront_id' => $this->subStorefrontId,
            'clinician_count'   => $clinicians->count(),
        ]);
    }
}
