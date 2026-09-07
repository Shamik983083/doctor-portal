<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllCliniciansForSubStorefrontJob;
use App\Models\Clinician;
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

        // Auto-create the Healthie user group if the partner has a configured API
        // key and no group_id was manually provided.
        $warning = $this->maybeCreateHealthieUserGroup($subStorefront);

        // Provision all eligible clinicians into the partner's Healthie org so
        // they appear as care team members when patients are pushed.
        if ($subStorefront->healthie_default_group_id) {
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

        // Mappings are now partner-level (sub_storefront_id IS NULL) — all clinicians
        // in a partner's Healthie org are shared across sub-storefronts.
        $clinicianMappings = ClinicianHealthieMapping::with('clinician.user')
            ->where('partner_id', $partner->id)
            ->whereNull('sub_storefront_id')
            ->orderBy('status')
            ->get();

        // Keyed by clinician_id for inline badge rendering in the view.
        $clinicianSyncStatus = $clinicianMappings->keyBy('clinician_id')
            ->map(fn ($m) => $m->status)
            ->all();

        $allClinicians        = Clinician::with('user')->where('status', 'active')->orderBy('id')->get();
        $assignedClinicianIds = $subStorefront->clinicians()->pluck('clinicians.id')->flip()->all();

        return view('admin.partners.sub-storefronts.edit', compact(
            'partner', 'subStorefront', 'clinicianMappings', 'clinicianSyncStatus',
            'allClinicians', 'assignedClinicianIds'
        ));
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
            // Clinician assignment: array of clinician IDs for the routing pool
            'clinician_ids'                => 'nullable|array',
            'clinician_ids.*'              => 'integer|exists:clinicians,id',
            // Must be one of the submitted clinician_ids (or null to clear)
            'collaborating_clinician_id'   => 'nullable|integer|exists:clinicians,id',
        ]);

        // Only non-global clinicians go into the pivot — globals are always eligible.
        $nonGlobalIds = Clinician::whereIn('id', $data['clinician_ids'] ?? [])
            ->where('is_global', false)
            ->pluck('id')
            ->all();

        // Collaborating default may be any active clinician (global or assigned).
        $collabId = $data['collaborating_clinician_id'] ?? null;

        $subStorefront->fill([
            'name'                         => $data['name'] ?? $subStorefront->name,
            'status'                       => $data['status'] ?? $subStorefront->status,
            'collaborating_clinician_id'   => $collabId,
            'healthie_endpoint'            => $data['healthie_endpoint'] ?? $subStorefront->healthie_endpoint,
            'healthie_authorization_shard' => $data['healthie_authorization_shard'] ?? $subStorefront->healthie_authorization_shard,
            'healthie_organization_id'     => $data['healthie_organization_id'] ?? $subStorefront->healthie_organization_id,
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

        // Sync only non-global clinicians into the pivot — globals are always eligible.
        $subStorefront->clinicians()->sync($nonGlobalIds);

        $warning = $this->maybeCreateHealthieUserGroup($subStorefront);

        // If a group is set, kick off clinician provisioning into the partner's org.
        if ($subStorefront->healthie_default_group_id) {
            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);
        }

        return redirect()
            ->route('admin.partners.sub-storefronts.index', $partner->id)
            ->with('success', "Sub-storefront \"{$subStorefront->name}\" updated.")
            ->with('healthie_warning', $warning);
    }

    /**
     * POST /admin/partners/{partnerId}/sub-storefronts/{subStorefront}/provision-group
     *
     * Re-attempts Healthie User Group creation for a sub-storefront that was
     * created before the partner had valid credentials, or whose auto-creation
     * failed at creation time.
     *
     * Safe to call on a sub-storefront that already has a group ID — it will
     * return immediately without calling Healthie again.
     */
    public function provisionGroup(int $partnerId, SubStorefront $subStorefront)
    {
        $partner = Partner::findOrFail($partnerId);
        abort_if($subStorefront->partner_id !== $partner->id, 403);

        if (! empty($subStorefront->healthie_default_group_id)) {
            return redirect()
                ->route('admin.partners.sub-storefronts.edit', [$partner->id, $subStorefront->id])
                ->with('info', "A Healthie group is already provisioned (ID: {$subStorefront->healthie_default_group_id}). No action taken.");
        }

        $partnerSettings = $partner->healthieSettings;

        if (! $partnerSettings || empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            return redirect()
                ->route('admin.partners.sub-storefronts.edit', [$partner->id, $subStorefront->id])
                ->with('error', 'Cannot provision group: the partner has no Healthie API key or endpoint configured. Set those on the partner edit page first.');
        }

        try {
            $service = app(HealthieProvisioningService::class);
            $groupId = $service->createUserGroup($subStorefront, $partnerSettings);
            $subStorefront->update(['healthie_default_group_id' => $groupId]);

            ProvisionAllCliniciansForSubStorefrontJob::dispatch($subStorefront->id);

            return redirect()
                ->route('admin.partners.sub-storefronts.edit', [$partner->id, $subStorefront->id])
                ->with('success', "Healthie User Group created successfully (ID: {$groupId}). Clinician provisioning is running in the background.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin: Healthie user group re-provision failed', [
                'sub_storefront_id' => $subStorefront->id,
                'error'             => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.partners.sub-storefronts.edit', [$partner->id, $subStorefront->id])
                ->with('error', 'Healthie group creation failed: ' . $e->getMessage());
        }
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
            ->with('warning', 'The corresponding Healthie User Group was NOT deleted automatically. Remove it from the Healthie admin portal manually if needed.');
    }

    /**
     * Attempt to auto-create a Healthie User Group for this sub-storefront.
     *
     * Uses the partner's single Healthie org credentials (PartnerEhrSetting).
     * Idempotent: skips when healthie_default_group_id is already set.
     * Non-fatal: catches all exceptions and returns a flash-safe warning string.
     */
    private function maybeCreateHealthieUserGroup(SubStorefront $subStorefront): ?string
    {
        if (! empty($subStorefront->healthie_default_group_id)) {
            return null;
        }

        $partnerSettings = $subStorefront->partner->healthieSettings;

        if (! $partnerSettings || empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            return 'Healthie user group was NOT auto-created: the parent partner has no Healthie API key configured. '
                . 'Enter the group_id manually, or configure the partner\'s Healthie credentials first.';
        }

        try {
            $service = app(HealthieProvisioningService::class);
            $groupId = $service->createUserGroup($subStorefront, $partnerSettings);

            $subStorefront->update(['healthie_default_group_id' => $groupId]);

            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SubStorefront: Healthie user group creation failed', [
                'sub_storefront_id' => $subStorefront->id,
                'error'             => $e->getMessage(),
            ]);

            return 'Healthie user group auto-creation failed: ' . $e->getMessage()
                . ' Enter the group_id manually once resolved.';
        }
    }
}
