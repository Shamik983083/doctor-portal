<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllCliniciansForSubStorefrontJob;
use App\Models\SubStorefront;
use App\Services\Ehr\HealthieProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubStorefrontController extends Controller
{
    /**
     * POST /api/partner/sub-storefronts
     *
     * Called by a tenant portal when a new tenant is registered.
     * Creates the sub-storefront in the doctor portal, provisions a Healthie
     * sub-organisation under the authenticated partner, and returns a one-time
     * temporary password for the Healthie admin user.
     *
     * The temporary password is generated here and returned once — it is never
     * stored. The calling system is responsible for relaying it to the new user.
     */
    public function store(Request $request)
    {
        $partner = $request->attributes->get('partner');

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
        ]);

        // Guard against accidental duplicates: a non-deleted sub-storefront with
        // the same name already exists for this partner. Return 409 with the
        // existing record so the caller can use its UUID without creating a second.
        $existing = SubStorefront::where('partner_id', $partner->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'message'           => 'A sub-storefront named "' . $existing->name . '" already exists for this partner.',
                'hint'              => 'Use the sub_storefront_id below in case submissions. If Healthie provisioning failed previously, edit the sub-storefront in the admin portal to set healthie_organization_id manually.',
                'sub_storefront_id' => $existing->uuid,
                'name'              => $existing->name,
                'slug'              => $existing->slug,
                'status'            => $existing->status,
                'healthie_organization_id' => $existing->healthie_organization_id,
            ], 409);
        }

        // Unique slug within this partner (including soft-deleted rows to avoid reuse)
        $slug     = Str::slug($data['name']);
        $existing = SubStorefront::withTrashed()
            ->where('partner_id', $partner->id)
            ->where('slug', $slug)
            ->count();
        if ($existing > 0) {
            $slug = $slug . '-' . ($existing + 1);
        }

        $subStorefront = SubStorefront::create([
            'partner_id' => $partner->id,
            'name'       => $data['name'],
            'slug'       => $slug,
            'status'     => 'active',
        ]);

        // Attempt to auto-provision the Healthie sub-org using the partner's API key.
        // The owner details (first_name, last_name, email) come from the tenant portal
        // rather than falling back to generic partner admin details.
        $healthie = $this->provisionHealthieSubOrg($subStorefront, $data);

        if ($healthie['org_id']) {
            $subStorefront->update(['healthie_organization_id' => $healthie['org_id']]);

            // Fan out clinician provisioning into the new sub-org
            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);
        }

        return response()->json([
            'sub_storefront_id' => $subStorefront->uuid,
            'name'              => $subStorefront->name,
            'slug'              => $subStorefront->slug,
            'status'            => $subStorefront->status,
            'healthie'          => [
                'organization_id'    => $healthie['org_id'],
                'admin_email'        => $data['email'],
                'temporary_password' => $healthie['temporary_password'],
                'error'              => $healthie['error'],
            ],
        ], 201);
    }

    /**
     * GET /api/partner/sub-storefronts
     *
     * List active sub-storefronts for the authenticated partner.
     */
    public function index(Request $request)
    {
        $partner = $request->attributes->get('partner');

        $subStorefronts = SubStorefront::where('partner_id', $partner->id)
            ->where('status', 'active')
            ->latest()
            ->get(['id', 'uuid', 'name', 'slug', 'status', 'healthie_organization_id', 'healthie_is_enabled', 'created_at']);

        return response()->json(
            $subStorefronts->map(fn ($sf) => [
                'sub_storefront_id'       => $sf->uuid,
                'name'                    => $sf->name,
                'slug'                    => $sf->slug,
                'status'                  => $sf->status,
                'healthie_organization_id' => $sf->healthie_organization_id,
                'healthie_enabled'        => $sf->healthie_is_enabled,
                'created_at'              => $sf->created_at->toIso8601String(),
            ])->values()
        );
    }

    /**
     * Try to create the Healthie sub-org. Non-fatal — returns error string instead
     * of throwing so a Healthie outage never blocks sub-storefront creation.
     */
    private function provisionHealthieSubOrg(SubStorefront $subStorefront, array $data): array
    {
        $partnerSettings = $subStorefront->partner->healthieSettings;

        if (! $partnerSettings || empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            return [
                'org_id'             => null,
                'temporary_password' => null,
                'error'              => 'Healthie sub-org was not created: the parent partner has no Healthie API key configured. '
                    . 'An admin must configure the partner\'s Healthie credentials and manually set the organization_id.',
            ];
        }

        try {
            $result = app(HealthieProvisioningService::class)->createSubOrgWithOwner(
                $subStorefront,
                $partnerSettings,
                $data['first_name'],
                $data['last_name'],
                $data['email'],
            );

            return [
                'org_id'             => $result['org_id'],
                'temporary_password' => $result['temporary_password'],
                'error'              => null,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('API SubStorefront: Healthie sub-org creation failed', [
                'sub_storefront_id' => $subStorefront->id,
                'error'             => $e->getMessage(),
            ]);

            return [
                'org_id'             => null,
                'temporary_password' => null,
                'error'              => 'Healthie sub-org auto-creation failed: ' . $e->getMessage()
                    . ' Set organization_id manually once resolved.',
            ];
        }
    }
}
