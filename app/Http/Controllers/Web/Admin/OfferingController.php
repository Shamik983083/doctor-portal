<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use App\Models\Questionnaire;
use App\Models\User;
use App\Notifications\NewOfferingCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferingController extends Controller
{
    private array $usStates = [
        'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
        'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
        'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
        'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
        'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
    ];

    public function index(Request $request)
    {
        $offerings = Offering::with(['partner', 'category'])
            ->when($request->partner_id, fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->active !== null && $request->active !== '', fn($q) => $q->where('is_active', (bool) $request->active))
            ->when($request->approval, fn($q, $a) => $q->where('approval_status', $a))
            ->latest()->paginate(20)->withQueryString();

        $partners = Partner::where('status', 'active')->get(['id', 'name']);

        return view('admin.offerings.index', compact('offerings', 'partners'));
    }

    private function attachableQuestionnaires()
    {
        $embeddedIds = Questionnaire::whereNotNull('linked_questionnaire_id')
            ->pluck('linked_questionnaire_id');

        return Questionnaire::where('is_active', true)
            ->whereNotIn('purpose', ['demographic', 'standard_intake'])
            ->whereNotIn('id', $embeddedIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create()
    {
        $partners          = Partner::where('status', 'active')->get(['id', 'name']);
        $categories        = OfferingCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $allQuestionnaires = $this->attachableQuestionnaires();
        $usStates          = $this->usStates;
        return view('admin.offerings.create', compact('partners', 'usStates', 'categories', 'allQuestionnaires'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'partner_id'              => 'required|exists:partners,id',
            'category_id'             => 'required|exists:offering_categories,id',
            'name'                    => 'required|string|max:255',
            'internal_name'           => 'nullable|string|max:255',
            'type'                    => 'required|in:medication,compound,supply',
            'description'             => 'nullable|string',
            'sku'                     => 'nullable|string|max:100',
            'price'                   => 'nullable|numeric|min:0',
            'compound_formula'        => 'required|string',
            'refills'                 => 'required|integer|min:0',
            'quantity'                => 'required|numeric|min:0',
            'days_supply'             => 'nullable|integer|min:0',
            'dispense_unit'           => 'nullable|string|max:100',
            'days_until_dispense'     => 'nullable|integer|min:0',
            'directions'              => 'required|string',
            'sig'                     => 'nullable|string',
            'levels'                  => 'nullable|array',
            'levels.*.label'          => 'required_with:levels|string|max:255',
            'levels.*.sig'            => 'required_with:levels|string',
            // 'pharmacy_type'        => 'required|in:boothwyn,curexa,custom', // not wired to live integration yet
            'pharmacy_type'           => 'nullable|in:boothwyn,curexa,custom',
            'pharmacy_name'           => 'nullable|string|max:255',
            'pharmacy_notes'          => 'nullable|string',
            // 'dosespot_medication_id' and 'boothwyn_compound_id' still accepted but not required
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'available_states.*'      => 'string|size:2',
            'video_required_states'   => 'nullable|array',
            'video_required_states.*' => 'string|size:2',
            'formulation_type'        => 'nullable|in:injectable,oral',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
            'questionnaire_ids'       => 'required|array|min:1',
            'questionnaire_ids.*'     => 'integer|exists:questionnaires,id',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');
        $data['category_id']             = $request->input('category_id') ?: null;

        // Build levels JSON if any were submitted, otherwise null out sig.
        $rawLevels = array_values(array_filter($request->input('levels', []), fn($l) => trim($l['label'] ?? '') !== ''));
        if (!empty($rawLevels)) {
            $data['levels'] = array_map(fn($l) => [
                'label'   => trim($l['label']),
                'formula' => '',
                'sig'     => trim($l['sig'] ?? ''),
            ], $rawLevels);
            $data['sig'] = null;
        } else {
            $data['levels'] = null;
            $rawSig = trim((string) $request->input('sig', ''));
            $data['sig'] = $rawSig !== '' ? $rawSig : null;
        }

        $data['approval_status']         = 'approved';
        $data['approved_by']             = Auth::id();
        $data['approved_at']             = now();

        $offering = Offering::create($data);

        // Auto-grant the owning partner access to their own offering.
        // Without this row in offering_partner, accessibleOfferings() never
        // returns the offering and case creation via product_key fails.
        if ($offering->partner_id) {
            $offering->partner->accessibleOfferings()->syncWithoutDetaching([
                $offering->id => ['is_active' => (bool) $data['is_active']],
            ]);
        }

        try {
            $offering->load('partner');
            User::role(['admin', 'super_admin'])->each(
                fn ($admin) => $admin->notify(new NewOfferingCreated($offering))
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Offering notification failed: ' . $e->getMessage());
        }

        $qIds = $request->input('questionnaire_ids', []);
        if (!empty($qIds)) {
            $requiredMap = $request->input('questionnaire_required', []);
            $sync = [];
            foreach (array_values($qIds) as $i => $qId) {
                $sync[(int) $qId] = [
                    'is_required' => isset($requiredMap[$qId]),
                    'sort_order'  => $i,
                ];
            }
            $offering->questionnaires()->sync($sync);
        }

        return redirect()->route('admin.offerings.show', $offering->id)
            ->with('success', "Offering \"{$offering->name}\" created successfully.");
    }

    public function show(int $id)
    {
        $offering          = Offering::with(['partner', 'category', 'questionnaires'])->findOrFail($id);
        $usStates          = $this->usStates;
        $categories        = OfferingCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $allQuestionnaires = $this->attachableQuestionnaires();
        return view('admin.offerings.show', compact('offering', 'usStates', 'categories', 'allQuestionnaires'));
    }

    public function attachQuestionnaire(Request $request, int $id)
    {
        $request->validate([
            'questionnaire_id' => 'required|integer|exists:questionnaires,id',
            'is_required'      => 'boolean',
        ]);

        $offering = Offering::findOrFail($id);

        $offering->questionnaires()->syncWithoutDetaching([
            $request->questionnaire_id => [
                'is_required' => $request->boolean('is_required', true),
                'sort_order'  => $offering->questionnaires()->count(),
            ],
        ]);

        return back()->with('success', 'Questionnaire attached.');
    }

    public function detachQuestionnaire(int $id, int $qId)
    {
        $offering = Offering::findOrFail($id);
        $offering->questionnaires()->detach($qId);

        return back()->with('success', 'Questionnaire removed.');
    }

    public function approve(int $id)
    {
        $offering = Offering::with('questionnaires')->findOrFail($id);

        if ($offering->questionnaires->count() === 0) {
            return back()->with('error', "Cannot approve \"{$offering->name}\" — please attach at least one Required Questionnaire first.");
        }

        $offering->update([
            'approval_status' => 'approved',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'rejection_note'  => null,
        ]);

        return back()->with('success', "\"{$offering->name}\" has been approved and is now available to clinicians.");
    }

    public function reject(Request $request, int $id)
    {
        $request->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $offering = Offering::findOrFail($id);
        $offering->update([
            'approval_status' => 'rejected',
            'approved_by'     => null,
            'approved_at'     => null,
            'rejection_note'  => $request->rejection_note,
        ]);

        return back()->with('success', "\"{$offering->name}\" has been rejected. The partner can edit and re-submit.");
    }

    public function toggleStatus(int $id)
    {
        $offering = Offering::findOrFail($id);
        $offering->update(['is_active' => !$offering->is_active]);

        return back()->with('success', "\"{$offering->name}\" marked " . ($offering->is_active ? 'active' : 'inactive') . '.');
    }

    public function destroy(int $id)
    {
        $offering = Offering::findOrFail($id);
        $name = $offering->name;

        $planCount = \App\Models\PartnerProductPlan::where('offering_id', $offering->id)->count();

        $offering->delete();

        if ($planCount > 0) {
            \App\Models\PartnerProductPlan::where('offering_id', $offering->id)->delete();
        }

        $msg = "Offering \"{$name}\" deleted.";
        if ($planCount > 0) {
            $msg .= " {$planCount} product plan row(s) were also removed.";
        }

        return redirect()->route('admin.offerings.index')
            ->with('success', $msg);
    }

    public function update(Request $request, int $id)
    {
        $offering = Offering::with('questionnaires')->findOrFail($id);

        if ($offering->questionnaires->count() === 0) {
            return back()->with('error', "Cannot save \"{$offering->name}\" — please attach at least one questionnaire first.");
        }

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'internal_name'           => 'nullable|string|max:255',
            'category_id'             => 'required|exists:offering_categories,id',
            'description'             => 'nullable|string',
            'price'                   => 'nullable|numeric|min:0',
            'compound_formula'        => 'required|string',
            'refills'                 => 'required|integer|min:0',
            'quantity'                => 'required|numeric|min:0',
            'days_supply'             => 'nullable|integer|min:0',
            'dispense_unit'           => 'nullable|string|max:100',
            'days_until_dispense'     => 'nullable|integer|min:0',
            'directions'              => 'required|string',
            // 'pharmacy_type'        => 'required|in:boothwyn,curexa,custom', // not wired to live integration yet
            'pharmacy_type'           => 'nullable|in:boothwyn,curexa,custom',
            'pharmacy_name'           => 'nullable|string|max:255',
            'pharmacy_notes'          => 'nullable|string',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'available_states.*'      => 'string|size:2',
            'video_required_states'   => 'nullable|array',
            'video_required_states.*' => 'string|size:2',
            'formulation_type'        => 'nullable|in:injectable,oral',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');
        $data['category_id']             = $request->input('category_id') ?: null;

        // Merge per-level SIG instructions into the levels JSON column.
        $currentLevels = $offering->levels;
        if (!empty($currentLevels) && is_array($currentLevels)) {
            $levelsSigs       = $request->input('levels_sigs', []);
            $levelsQuantities = $request->input('levels_quantities', []);
            $updatedLevels = [];
            foreach ($currentLevels as $idx => $level) {
                $level['sig'] = trim((string) ($levelsSigs[$idx] ?? ''));
                $qty = trim((string) ($levelsQuantities[$idx] ?? ''));
                $level['quantity'] = $qty !== '' ? (float) $qty : null;
                $updatedLevels[] = $level;
            }
            $data['levels'] = $updatedLevels;
            $data['sig']    = null;
        } else {
            $rawSig = trim((string) $request->input('sig', ''));
            $data['sig'] = $rawSig !== '' ? $rawSig : null;
        }

        $offering->update($data);

        // Keep offering_partner.is_active in sync when the offering's active
        // flag changes, so the accessible offering gate stays consistent.
        if ($offering->partner_id) {
            $offering->partner->accessibleOfferings()->syncWithoutDetaching([
                $offering->id => ['is_active' => (bool) $data['is_active']],
            ]);
        }

        return back()->with('success', 'Offering updated.');
    }
}
