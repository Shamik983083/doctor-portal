<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Partner;
use Illuminate\Http\Request;

class OfferingController extends Controller
{
    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request)
    {
        $offerings = $this->partner($request)->offerings()
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->active !== null, fn($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->state, fn($q, $s) => $q->whereJsonContains('available_states', strtoupper($s)))
            ->paginate(25);

        return response()->json($offerings);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'type'                    => 'required|in:medication,compound,supply',
            'description'             => 'nullable|string',
            'sku'                     => 'nullable|string',
            'price'                   => 'nullable|numeric|min:0',
            'dosespot_medication_id'  => 'nullable|string',
            'boothwyn_compound_id'    => 'nullable|string',
            'pharmacy_type'           => 'nullable|string',
            'available_states'        => 'nullable|array',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
            'metadata'                => 'nullable|array',
        ]);

        $offering = $this->partner($request)->offerings()->create($data);

        return response()->json($offering, 201);
    }

    public function show(Request $request, string $id)
    {
        $offering = $this->partner($request)->offerings()->where('uuid', $id)->firstOrFail();

        return response()->json($offering);
    }

    public function update(Request $request, string $id)
    {
        $offering = $this->partner($request)->offerings()->where('uuid', $id)->firstOrFail();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'type'             => 'sometimes|in:medication,compound,supply',
            'description'      => 'nullable|string',
            'price'            => 'nullable|numeric|min:0',
            'available_states' => 'nullable|array',
            'is_active'        => 'boolean',
            'metadata'         => 'nullable|array',
        ]);

        $offering->update($data);

        return response()->json($offering);
    }

    public function destroy(Request $request, string $id)
    {
        $offering = $this->partner($request)->offerings()->where('uuid', $id)->firstOrFail();
        $offering->delete();

        return response()->json(['message' => 'Offering deleted.']);
    }
}
