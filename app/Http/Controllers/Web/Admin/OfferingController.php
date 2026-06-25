<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Partner;
use Illuminate\Http\Request;

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
        $offerings = Offering::with('partner')
            ->when($request->partner_id, fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()->paginate(20);

        $partners = Partner::where('status', 'active')->get(['id', 'name']);

        return view('admin.offerings.index', compact('offerings', 'partners'));
    }

    public function create()
    {
        $partners = Partner::where('status', 'active')->get(['id', 'name']);
        $usStates = $this->usStates;
        return view('admin.offerings.create', compact('partners', 'usStates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'partner_id'              => 'required|exists:partners,id',
            'name'                    => 'required|string|max:255',
            'type'                    => 'required|in:medication,compound,supply',
            'description'             => 'nullable|string',
            'sku'                     => 'nullable|string|max:100',
            'price'                   => 'nullable|numeric|min:0',
            'pharmacy_type'           => 'nullable|in:boothwyn,curexa,custom',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'available_states.*'      => 'string|size:2',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        // Checkboxes send nothing when unchecked — default to false
        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');

        Offering::create($data);

        return redirect()->route('admin.offerings.index')
            ->with('success', "Offering \"{$data['name']}\" created successfully.");
    }

    public function show(int $id)
    {
        $offering = Offering::with('partner')->findOrFail($id);
        $usStates = $this->usStates;
        return view('admin.offerings.show', compact('offering', 'usStates'));
    }

    public function update(Request $request, int $id)
    {
        $offering = Offering::findOrFail($id);

        $data = $request->validate([
            'name'                    => 'sometimes|string|max:255',
            'description'             => 'nullable|string',
            'price'                   => 'nullable|numeric|min:0',
            'pharmacy_type'           => 'nullable|in:boothwyn,curexa,custom',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'available_states.*'      => 'string|size:2',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');

        $offering->update($data);

        return back()->with('success', 'Offering updated.');
    }
}
