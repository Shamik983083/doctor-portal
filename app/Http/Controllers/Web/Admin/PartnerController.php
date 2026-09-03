<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllCliniciansForPartnerJob;
use App\Models\Clinician;
use App\Models\ClinicianHealthieMapping;
use App\Models\Partner;
use App\Models\PartnerEhrSetting;
use App\Models\User;
use App\Services\Ehr\HealthieProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;

class PartnerController extends Controller
{
    private const EVENT_TYPES = [
        'case_waiting', 'case_assigned_to_clinician', 'case_support',
        'case_approved', 'case_processing', 'case_completed', 'case_cancelled',
        'prescription_written', 'message_created',
    ];

    public function index(Request $request)
    {
        $partners = Partner::withCount(['patients', 'cases'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);

        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        return view('admin.partners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:partners',
            'phone'       => 'nullable|string',
            'website'     => 'nullable|url',
            'description' => 'nullable|string',

            // Healthie values, captured at company creation so a new storefront
            // is configured to push from the moment it exists rather than being
            // wired up later and quietly failing in between.
            'healthie_api_key'             => 'nullable|string|max:500',
            'healthie_endpoint'            => 'nullable|url|max:255',
            'healthie_authorization_shard' => 'nullable|string|max:255',
            'healthie_organization_id'     => 'nullable|string|max:255',
            'healthie_default_provider_id' => 'nullable|string|max:255',
            'healthie_note_form_id'        => 'nullable|string|max:255',
            'healthie_default_group_id'    => 'nullable|string|max:255',
        ]);

        $partnerData = collect($data)->except([
            'healthie_api_key', 'healthie_endpoint', 'healthie_authorization_shard',
            'healthie_organization_id', 'healthie_default_provider_id', 'healthie_note_form_id',
            'healthie_default_group_id',
        ])->all();

        $partnerData['slug'] = Str::slug($partnerData['name']);

        $partner = Partner::create($partnerData);

        $this->saveHealthieSettings($partner, $request);

        // Auto-create Healthie sub-org if API key was provided at creation time.
        $this->maybeCreateHealthieSubOrg($partner);

        // Create Passport client for this partner
        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createClientCredentialsGrantClient(
            $partner->name . ' API Client'
        );

        $partner->update([
            'oauth_client_id' => $client->id,
            'client_id'       => $client->id,
            'client_secret'   => $client->plainSecret ?? $client->secret,
        ]);

        $warning = $this->healthieConfigWarning($partner);

        return redirect()->route('admin.partners.index')
            ->with('success', "Partner created. Client ID: {$client->id}")
            ->with('warning', $warning);
    }

    /**
     * Persist this company's Healthie values.
     *
     * ALWAYS creates the settings row, even when the fields were left blank, so
     * every company has one place its EHR configuration lives and a half-set-up
     * storefront is visible as incomplete rather than absent. The row starts
     * disabled: turning a company on is a deliberate act after its sandbox has
     * been checked, never a side effect of creating it.
     *
     * The API key is encrypted by the model cast. Blank input never overwrites a
     * stored key, so editing a company without retyping the secret does not wipe it.
     */
    private function saveHealthieSettings(Partner $partner, Request $request): PartnerEhrSetting
    {
        $settings = PartnerEhrSetting::firstOrNew([
            'partner_id' => $partner->id,
            'provider'   => 'healthie',
        ]);

        $settings->fill([
            'endpoint'            => $request->input('healthie_endpoint') ?: $settings->endpoint,
            'authorization_shard' => $request->input('healthie_authorization_shard') ?: $settings->authorization_shard,
            'organization_id'     => $request->input('healthie_organization_id') ?: $settings->organization_id,
            'default_provider_id' => $request->input('healthie_default_provider_id') ?: $settings->default_provider_id,
            'note_form_id'        => $request->input('healthie_note_form_id') ?: $settings->note_form_id,
            'default_group_id'    => $request->input('healthie_default_group_id') ?: $settings->default_group_id,
        ]);

        if ($request->filled('healthie_api_key')) {
            $settings->api_key = $request->input('healthie_api_key');
        }

        $settings->partner_id = $partner->id;
        $settings->provider   = 'healthie';
        $settings->save();

        return $settings;
    }

    /**
     * Tell the admin plainly if this company cannot push yet, at the moment they
     * create it. A storefront that silently previews forever because a field was
     * missed is the failure this exists to prevent.
     */
    private function healthieConfigWarning(Partner $partner): ?string
    {
        $settings = PartnerEhrSetting::where('partner_id', $partner->id)->where('provider', 'healthie')->first();

        if (! $settings) {
            return null;
        }

        $missing = $settings->missingValues();

        if ($missing === []) {
            if ($settings->isPushable()) {
                return null;
            }
            return 'Healthie values saved. The company is still disabled for push: enable it once its sandbox '
                . 'has been validated. See docs/integrations/HEALTHIE-SETUP.md.';
        }

        return 'Healthie is not fully configured for this company (missing: ' . implode(', ', $missing)
            . '). Records will be built and stored for preview but nothing will be pushed. '
            . 'See docs/integrations/HEALTHIE-SETUP.md.';
    }

    public function show(int $id)
    {
        $partner = Partner::withCount(['patients', 'cases', 'offerings'])
            ->with(['users', 'webhooks' => fn($q) => $q->latest()])
            ->findOrFail($id);
        $partner->makeVisible('client_secret');
        return view('admin.partners.show', compact('partner'));
    }

    public function edit(int $id)
    {
        $partner = Partner::findOrFail($id);
        $clinicians = Clinician::with('user')->orderBy('id')->get();
        return view('admin.partners.edit', compact('partner', 'clinicians'));
    }

    public function update(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'phone'       => 'nullable|string',
            'website'     => 'nullable|url',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,suspended,inactive',

            'healthie_api_key'             => 'nullable|string|max:500',
            'healthie_endpoint'            => 'nullable|url|max:255',
            'healthie_authorization_shard' => 'nullable|string|max:255',
            'healthie_organization_id'     => 'nullable|string|max:255',
            'healthie_default_provider_id' => 'nullable|string|max:255',
            'healthie_note_form_id'        => 'nullable|string|max:255',
            'healthie_default_group_id'    => 'nullable|string|max:255',

            // Enabling push for a company is deliberate and separate from
            // entering its values, so a paste of credentials never switches a
            // storefront live by itself.
            'healthie_is_enabled'        => 'nullable|boolean',
            'healthie_sandbox_validated' => 'nullable|boolean',

            // E19: default collaborating clinician for new patients from this storefront
            'collaborating_clinician_id' => [
                'nullable',
                Rule::exists('clinicians', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $partner->update(collect($data)->reject(fn ($v, $k) => str_starts_with($k, 'healthie_'))->all());

        $settings = $this->saveHealthieSettings($partner, $request);

        $settings->update([
            'is_enabled'        => $request->boolean('healthie_is_enabled'),
            'sandbox_validated' => $request->boolean('healthie_sandbox_validated'),
        ]);

        // Fallback: if sub-org creation was missed at create time (e.g. API key
        // was not filled in then), create it now that credentials are present.
        $this->maybeCreateHealthieSubOrg($partner);

        return redirect()->route('admin.partners.index')
            ->with('success', 'Partner updated.')
            ->with('warning', $this->healthieConfigWarning($partner));
    }

    public function createUser(int $id)
    {
        $partner = Partner::findOrFail($id);
        return view('admin.partners.create-user', compact('partner'));
    }

    public function storeUser(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'partner_id' => $partner->id,
        ]);

        $user->assignRole('partner');

        return redirect()->route('admin.partners.show', $partner->id)
            ->with('success', "Partner user {$user->email} created. They can now log in at /login.");
    }

    public function regenerateCredentials(int $id)
    {
        $partner = Partner::findOrFail($id);

        // Revoke old Passport client if exists
        if ($partner->oauth_client_id) {
            \Laravel\Passport\Client::find($partner->oauth_client_id)?->delete();
        }

        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createClientCredentialsGrantClient(
            $partner->name . ' API Client'
        );

        $partner->update([
            'oauth_client_id' => $client->id,
            'client_id'       => $client->id,
            'client_secret'   => $client->plainSecret ?? $client->secret,
        ]);

        return redirect()->route('admin.partners.show', $partner->id)
            ->with('success', 'API credentials regenerated. Share the new secret with the partner immediately · it cannot be retrieved again.');
    }

    public function storeWebhook(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'url'        => ['required', 'url'],
            'event_type' => ['nullable', Rule::in(self::EVENT_TYPES)],
        ]);

        $partner->webhooks()->create([
            'url'        => $data['url'],
            'event_type' => $data['event_type'] ?? null,
            'status'     => 'active',
        ]);

        return redirect()->route('admin.partners.show', $partner->id)->with('success', 'Webhook added.');
    }

    public function updateWebhook(int $id, int $webhookId)
    {
        $partner = Partner::findOrFail($id);
        $webhook = $partner->webhooks()->findOrFail($webhookId);

        $webhook->update([
            'status' => $webhook->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->route('admin.partners.show', $partner->id)->with('success', 'Webhook status updated.');
    }

    public function destroy(int $id)
    {
        $partner = Partner::findOrFail($id);

        // Soft-delete does not fire DB-level FK cascades, so clean up orphan
        // mapping rows explicitly before the partner record disappears.
        ClinicianHealthieMapping::where('partner_id', $partner->id)->delete();

        $partner->delete();

        return redirect()->route('admin.partners.index')
            ->with('success', "Partner {$partner->name} has been deleted.");
    }

    public function destroyWebhook(int $id, int $webhookId)
    {
        $partner = Partner::findOrFail($id);
        $webhook = $partner->webhooks()->findOrFail($webhookId);

        $webhook->delete();

        return redirect()->route('admin.partners.show', $partner->id)->with('success', 'Webhook deleted.');
    }

    /**
     * Create a Healthie sub-org for this partner if:
     *   - they have an API key saved (credentials exist), AND
     *   - they do NOT already have an organization_id (hasn't been created yet).
     *
     * On success: stores the returned org ID and dispatches provider provisioning.
     * On failure: logs the error and flashes a warning — the partner record is
     * still saved; admin can trigger a retry by saving EHR settings again.
     */
    private function maybeCreateHealthieSubOrg(Partner $partner): void
    {
        $settings = PartnerEhrSetting::where('partner_id', $partner->id)
            ->where('provider', 'healthie')
            ->first();

        // No credentials yet, or org already exists — nothing to do.
        if (! $settings || empty($settings->api_key) || ! empty($settings->organization_id)) {
            return;
        }

        // Parent API key required for sub-org creation.
        if (empty(config('ehr.healthie.parent_api_key'))) {
            return;
        }

        try {
            $service = app(HealthieProvisioningService::class);
            $orgId   = $service->createSubOrganization($partner);

            $settings->update(['organization_id' => $orgId]);

            // Provision all active global clinicians into the new sub-org.
            ProvisionAllCliniciansForPartnerJob::dispatch($partner->id);
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: sub-org creation failed', [
                'partner_id' => $partner->id,
                'error'      => $e->getMessage(),
            ]);

            session()->flash('healthie_warning',
                'Partner saved, but Healthie sub-org creation failed: ' . $e->getMessage()
                . ' — save the partner\'s EHR settings again to retry.'
            );
        }
    }

    /**
     * Proxy a Healthie lookup for forms and groups using the stored credentials.
     * Never exposes the API key to the browser — key stays server-side.
     */
    public function healthieLookup(int $id): JsonResponse
    {
        $partner  = Partner::findOrFail($id);
        $settings = PartnerEhrSetting::where('partner_id', $partner->id)
            ->where('provider', 'healthie')
            ->first();

        if (! $settings || ! $settings->api_key || ! $settings->endpoint) {
            return response()->json([
                'error' => 'No API key or endpoint is saved for this partner yet. Save those fields first.',
            ], 422);
        }

        $gql = <<<'GQL'
        {
            customModuleForms { id name }
            userGroups { id name }
        }
        GQL;

        try {
            $response = Http::withHeaders($settings->authHeaders())
                ->acceptJson()
                ->timeout(15)
                ->post($settings->endpoint, ['query' => $gql]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not reach Healthie: ' . $e->getMessage()], 422);
        }

        if (! $response->successful()) {
            return response()->json(['error' => 'Healthie returned HTTP ' . $response->status()], 422);
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'], $json['errors']));
            return response()->json(['error' => $msg], 422);
        }

        return response()->json([
            'forms'  => $json['data']['customModuleForms'] ?? [],
            'groups' => $json['data']['userGroups'] ?? [],
        ]);
    }
}
