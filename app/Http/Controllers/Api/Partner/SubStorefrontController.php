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
     * Creates the sub-storefront in the doctor portal and provisions a Healthie
     * User Group within the authenticated partner's single Healthie organisation.
     *
     * The first_name/last_name/email fields are accepted for future use but are
     * no longer required to provision a Healthie admin user — User Groups do not
     * have owners; individual clinicians are added to care teams per-patient.
     */
    public function store(Request $request)
    {
        $partner = $request->attributes->get('partner');

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'nullable|email|max:255',
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
                'hint'              => 'Use the sub_storefront_id below in case submissions. If Healthie provisioning failed previously, edit the sub-storefront in the admin portal to set healthie_default_group_id manually.',
                'sub_storefront_id' => $existing->uuid,
                'name'              => $existing->name,
                'slug'              => $existing->slug,
                'status'            => $existing->status,
                'healthie_group_id' => $existing->healthie_default_group_id,
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

        // Attempt to auto-provision the Healthie user group using the partner's API key.
        $healthie = $this->provisionHealthieUserGroup($subStorefront);

        if ($healthie['group_id']) {
            $subStorefront->update(['healthie_default_group_id' => $healthie['group_id']]);

            // Fan out clinician provisioning into the partner's Healthie org
            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);
        }

        return response()->json([
            'sub_storefront_id' => $subStorefront->uuid,
            'name'              => $subStorefront->name,
            'slug'              => $subStorefront->slug,
            'status'            => $subStorefront->status,
            'healthie'          => [
                'group_id' => $healthie['group_id'],
                'error'    => $healthie['error'],
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
            ->get(['id', 'uuid', 'name', 'slug', 'status', 'healthie_default_group_id', 'healthie_is_enabled', 'created_at']);

        return response()->json(
            $subStorefronts->map(fn ($sf) => [
                'sub_storefront_id' => $sf->uuid,
                'name'              => $sf->name,
                'slug'              => $sf->slug,
                'status'            => $sf->status,
                'healthie_group_id' => $sf->healthie_default_group_id,
                'healthie_enabled'  => $sf->healthie_is_enabled,
                'created_at'        => $sf->created_at->toIso8601String(),
            ])->values()
        );
    }

    /**
     * Create a Healthie User Group for the sub-storefront using the partner's API key.
     * Non-fatal — returns error string instead of throwing so a Healthie outage
     * never blocks sub-storefront creation.
     */
    private function provisionHealthieUserGroup(SubStorefront $subStorefront): array
    {
        $partnerSettings = $subStorefront->partner->healthieSettings;

        if (! $partnerSettings || empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            return [
                'group_id' => null,
                'error'    => 'Healthie user group was not created: the parent partner has no Healthie API key configured. '
                    . 'An admin must configure the partner\'s Healthie credentials and manually set the group_id.',
            ];
        }

        try {
            $groupId = app(HealthieProvisioningService::class)
                ->createUserGroup($subStorefront, $partnerSettings);

            return ['group_id' => $groupId, 'error' => null];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('API SubStorefront: Healthie user group creation failed', [
                'sub_storefront_id' => $subStorefront->id,
                'error'             => $e->getMessage(),
            ]);

            return [
                'group_id' => null,
                'error'    => 'Healthie user group auto-creation failed: ' . $e->getMessage()
                    . ' Set group_id manually once resolved.',
            ];
        }
    }
}
