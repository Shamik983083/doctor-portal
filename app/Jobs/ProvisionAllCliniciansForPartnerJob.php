<?php

namespace App\Jobs;

use App\Models\Clinician;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * When a new partner sub-org is created in Healthie, dispatch individual
 * provisioning jobs for every active global clinician.
 *
 * Fanning out to individual jobs keeps each clinician's failure isolated:
 * a bad email on one provider doesn't abort provisioning for the rest.
 */
class ProvisionAllCliniciansForPartnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $partnerId) {}

    public function handle(): void
    {
        Clinician::where('is_global', true)
            ->where('status', 'active')
            ->get()
            ->each(fn (Clinician $clinician) => ProvisionClinicianInHealthieJob::dispatch($clinician));
    }
}
