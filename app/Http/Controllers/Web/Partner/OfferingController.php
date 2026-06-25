<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Models\Offering;
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

    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $offerings = $this->partner()->offerings()
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()->paginate(20);

        return view('partner.offerings.index', compact('offerings'));
    }

    public function create()
    {
        $usStates = $this->usStates;
        return view('partner.offerings.create', compact('usStates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');

        $offering = $this->partner()->offerings()->create($data);

        return redirect()->route('partner.offerings.show', $offering->id)
            ->with('success', "Offering \"{$offering->name}\" created successfully.");
    }

    public function show(int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);
        $usStates = $this->usStates;
        return view('partner.offerings.show', compact('offering', 'usStates'));
    }

    public function update(Request $request, int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);

        $data = $request->validate([
            'name'                    => 'sometimes|string|max:255',
            'description'             => 'nullable|string',
            'price'                   => 'nullable|numeric|min:0',
            'pharmacy_type'           => 'nullable|in:boothwyn,curexa,custom',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');

        $offering->update($data);

        return back()->with('success', 'Offering updated.');
    }

    public function destroy(int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);
        $offering->delete();

        return redirect()->route('partner.offerings.index')
            ->with('success', 'Offering deleted.');
    }
}
