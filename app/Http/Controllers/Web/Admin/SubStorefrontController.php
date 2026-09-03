<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllCliniciansForSubStorefrontJob;
use App\Models\ClinicianHealthieMapping;
use App\Models\Partner;
use App\Models\SubStorefront;
use App\Services\Ehr\HealthieProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubStorefrontController extends Controller
{
    public function index(int $partnerId)
    {
        $partner        = Partner::findOrFail($partnerId);
        $subStorefronts = $partner->subStorefronts()->withTrashed(false)->latest()->get();

        return view('admin.partners.sub-storefronts.index', compact('partner', 'subStorefronts'));
    }

    public function create(int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        return view('admin.partners.sub-storefronts.create', compact('partner'));
    }

    public function store(Request $request, int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        $data = $request->validate([
            'name'                         => 'required|string|max:255',
            'status'                       => 'nullable|in:active,suspended,inactive',
            'healthie_api_key'             => 'nullable|string|max:500',
            'healthie_endpoint'            => 'nullable|url|max:255',
            'healthie_authorization_shard' => 'nullable|string|max:255',
            'healthie_organization_id'     => 'nullable|string|max:255',
            'healthie_default_provider_id' => 'nullable|string|max:255',
            'healthie_note_form_id'        => 'nullable|string|max:255',
            'healthie_default_group_id'    => 'nullable|string|max:255',
            'healthie_is_enabled'          => 'nullable|boolean',
            'healthie_sandbox_validated'   => 'nullable|boolean',
        ]);

        $slug = Str::slug($data['name']);

        // Ensure slug uniqueness within this partner
        $existing = SubStorefront::withTrashed()
            ->where('partner_id', $partner->id)
            ->where('slug', $slug)
            ->count();
        if ($existing > 0) {
            $slug = $slug . '-' . ($existing + 1);
        }

        $subStorefront = new SubStorefront([
            'partner_id' => $partner->id,
            'name'       => $data['name'],
            'slug'       => $slug,
            'status'     => $data['status'] ?? 'active',
            'healthie_endpoint'            => $data['healthie_endpoint'] ?? null,
            'healthie_authorization_shard' => $data['healthie_authorization_shard'] ?? null,
            'healthie_organization_id'     => $data['healthie_organization_id'] ?? null,
            'healthie_default_provider_id' => $data['healthie_default_provider_id'] ?? null,
            'healthie_note_form_id'        => $data['healthie_note_form_id'] ?? null,
            'healthie_default_group_id'    => $data['healthie_default_group_id'] ?? null,
            'healthie_is_enabled'          => $request->boolean('healthie_is_enabled'),
            'healthie_sandbox_validated'   => $request->boolean('healthie_sandbox_validated'),
        ]);

        if ($request->filled('healthie_api_key')) {
            $subStorefront->healthie_api_key = $data['healthie_api_key'];
        }

        $subStorefront->save();

        // Auto-create Healthie sub-org if the partner has a configured API key and
        // no organization_id was manually provided.
        $warning = $this->maybeCreateHealthieSubOrg($subStorefront);

        // Provision all global clinicians into this new sub-org.
        if ($subStorefront->healthie_organization_id) {
            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);
        }

        return redirect()
            ->route('admin.partners.sub-storefronts.index', $partner->id)
            ->with('success', "Sub-storefront \"{$subStorefront->name}\" created.")
            ->with('warning', $warning)
            ->with('healthie_warning', $warning);
    }

    public function edit(int $partnerId, SubStorefront $subStorefront)
    {
        $partner = Partner::findOrFail($partnerId);
        abort_if($subStorefront->partner_id !== $partner->id, 403);

        $clinicianMappings = ClinicianHealthieMapping::with('clinician.user')
            ->where('sub_storefront_id', $subStorefront->id)
            ->orderBy('status')
            ->get();

        return view('admin.partners.sub-storefronts.edit', compact('partner', 'subStorefront', 'clinicianMappings'));
    }

    public function update(Request $request, int $partnerId, SubStorefront $subStorefront)
    {
        $partner = Partner::findOrFail($partnerId);
        abort_if($subStorefront->partner_id !== $partner->id, 403);

        $data = $request->validate([
            'name'                         => 'sometimes|required|string|max:255',
            'status'                       => 'nullable|in:active,suspended,inactive',
            'healthie_api_key'             => 'nullable|string|max:500',
            'healthie_endpoint'            => 'nullable|url|max:255',
            'healthie_authorization_shard' => 'nullable|string|max:255',
            'healthie_organization_id'     => 'nullable|string|max:255',
            'healthie_default_provider_id' => 'nullable|string|max:255',
            'healthie_note_form_id'        => 'nullable|string|max:255',
            'healthie_default_group_id'    => 'nullable|string|max:255',
            'healthie_is_enabled'          => 'nullable|boolean',
            'healthie_sandbox_validated'   => 'nullable|boolean',
        ]);

        $subStorefront->fill([
            'name'                         => $data['name'] ?? $subStorefront->name,
            'status'                       => $data['status'] ?? $subStorefront->status,
            'healthie_endpoint'            => $data['healthie_endpoint'] ?: $subStorefront->healthie_endpoint,
            'healthie_authorization_shard' => $data['healthie_authorization_shard'] ?: $subStorefront->healthie_authorization_shard,
            'healthie_organization_id'     => $data['healthie_organization_id'] ?: $subStorefront->healthie_organization_id,
            'healthie_default_provider_id' => $data['healthie_default_provider_id'] ?: $subStorefront->healthie_default_provider_id,
            'healthie_note_form_id'        => $data['healthie_note_form_id'] ?: $subStorefront->healthie_note_form_id,
            'healthie_default_group_id'    => $data['healthie_default_group_id'] ?: $subStorefront->healthie_default_group_id,
            'healthie_is_enabled'          => $request->boolean('healthie_is_enabled'),
            'healthie_sandbox_validated'   => $request->boolean('healthie_sandbox_validated'),
        ]);

        // Blank API key input = keep the stored encrypted value
        if ($request->filled('healthie_api_key')) {
            $subStorefront->healthie_api_key = $data['healthie_api_key'];
        }

        $subStorefront->save();

        $warning = $this->maybeCreateHealthieSubOrg($subStorefront);

        // If org was just created, kick off clinician provisioning
        if ($subStorefront->healthie_organization_id) {
            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);
        }

        return redirect()
            ->route('admin.partners.sub-storefronts.index', $partner->id)
            ->with('success', "Sub-storefront \"{$subStorefront->name}\" updated.")
            ->with('healthie_warning', $warning);
    }

    public function destroy(int $partnerId, SubStorefront $subStorefront)
    {
        $partner = Partner::findOrFail($partnerId);
        abort_if($subStorefront->partner_id !== $partner->id, 403);

        // Soft-delete does not fire DB-level FK cascades, so clean up orphan
        // mapping rows explicitly before the sub-storefront record disappears.
        ClinicianHealthieMapping::where('sub_storefront_id', $subStorefront->id)->delete();

        $subStorefront->delete();

        return redirect()
            ->route('admin.partners.sub-storefronts.index', $partner->id)
            ->with('success', "Sub-storefront \"{$subStorefront->name}\" has been removed.")
            ->with('warning', 'The corresponding Healthie sub-organisation was NOT deleted automatically. Remove it from Healthie manually if needed.');
    }

    /**
     * Attempt to auto-create the Healthie sub-org using the PARTNER's API key
     * (the partner is the parent org in Healthie; sub-storefronts are sub-orgs under it).
     * Idempotent: skips when healthie_organization_id is already set.
     * Non-fatal: catches all exceptions and returns a flash-safe warning string.
     */
    private function maybeCreateHealthieSubOrg(SubStorefront $subStorefront): ?string
    {
        if (! empty($subStorefront->healthie_organization_id)) {
            return null;
        }

        // Use the partner's PartnerEhrSetting API key as the acting credential.
        $partnerSettings = $subStorefront->partner->healthieSettings;

        if (! $partnerSettings || empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            return 'Healthie sub-org was NOT auto-created: the parent partner has no Healthie API key configured. '
                . 'Enter the organization_id manually, or configure the partner\'s Healthie credentials first.';
        }

        try {
            $service = app(HealthieProvisioningService::class);
            $orgId   = $service->createSubOrgForStorefront($subStorefront, $partnerSettings);

            $subStorefront->update(['healthie_organization_id' => $orgId]);

            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SubStorefront: Healthie sub-org creation failed', [
                'sub_storefront_id' => $subStorefront->id,
                'error'             => $e->getMessage(),
            ]);

            return 'Healthie sub-org auto-creation failed: ' . $e->getMessage()
                . ' Enter the organization_id manually once resolved.';
        }
    }
}
