<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Partner;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientController extends Controller
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request)
    {
        $patients = $this->partner($request)->patients()
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->paginate(25);

        return response()->json($patients);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'external_id'  => 'nullable|string|max:255',
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'required|email',
            'phone'        => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender'       => 'nullable|in:male,female,other',
            'address'      => 'nullable|string',
            'address2'     => 'nullable|string',
            'city'         => 'nullable|string',
            'state'        => 'nullable|string|size:2',
            'zip'          => 'nullable|string|max:10',
        ]);

        $partner = $this->partner($request);

        if ($data['external_id'] ?? null) {
            $existing = $partner->patients()->where('external_id', $data['external_id'])->first();
            if ($existing) {
                return response()->json($existing, 200);
            }
        }

        $patient = $partner->patients()->create($data);

        $this->webhooks->dispatch($partner->id, 'patient_created', [
            'patient_id' => $patient->uuid,
            'timestamp'  => now()->timestamp,
        ]);

        return response()->json($patient, 201);
    }

    public function show(Request $request, string $id)
    {
        $patient = $this->partner($request)->patients()
            ->where('uuid', $id)->firstOrFail();

        return response()->json($patient->load(['cases', 'orders', 'preferredPharmacies', 'tags']));
    }

    public function showByExternalId(Request $request, string $externalId)
    {
        $patient = $this->partner($request)->patients()
            ->where('external_id', $externalId)->firstOrFail();

        return response()->json($patient);
    }

    public function update(Request $request, string $id)
    {
        $patient = $this->partner($request)->patients()
            ->where('uuid', $id)->firstOrFail();

        $data = $request->validate([
            'first_name'   => 'sometimes|string|max:100',
            'last_name'    => 'sometimes|string|max:100',
            'email'        => 'sometimes|email',
            'phone'        => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender'       => 'nullable|in:male,female,other',
            'address'      => 'nullable|string',
            'city'         => 'nullable|string',
            'state'        => 'nullable|string|size:2',
            'zip'          => 'nullable|string|max:10',
        ]);

        $patient->update($data);

        $this->webhooks->dispatch($patient->partner_id, 'patient_modified', [
            'patient_id' => $patient->uuid,
            'timestamp'  => now()->timestamp,
        ]);

        return response()->json($patient);
    }

    public function destroy(Request $request, string $id)
    {
        $patient = $this->partner($request)->patients()
            ->where('uuid', $id)->firstOrFail();

        $patient->update(['status' => 'deleted']);
        $patient->delete();

        $this->webhooks->dispatch($patient->partner_id, 'patient_deleted', [
            'patient_id' => $id,
            'timestamp'  => now()->timestamp,
        ]);

        return response()->json(['message' => 'Patient deleted.'], 200);
    }
}
