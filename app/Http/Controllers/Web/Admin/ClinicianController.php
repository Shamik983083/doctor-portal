<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClinicianController extends Controller
{
    public function index(Request $request)
    {
        $clinicians = Clinician::with('user')
            ->withCount(['cases'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($q) => $q->where('name', 'like', "%{$s}%")))
            ->paginate(20);

        return view('admin.clinicians.index', compact('clinicians'));
    }

    public function create()
    {
        return view('admin.clinicians.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string',
            'email'           => 'required|email|unique:users',
            'password'        => 'required|min:8|confirmed',
            'npi'             => 'nullable|string',
            'license_number'  => 'nullable|string',
            'license_state'   => 'nullable|string|size:2',
            'specialty'       => 'nullable|string',
            'credentials'     => 'nullable|in:MD,DO,NP,PA',
            'licensed_states'   => 'nullable|array',
            'licensed_states.*' => 'string|size:2',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole('clinician');

        $clinician = Clinician::create([
            'user_id'         => $user->id,
            'npi'             => $data['npi'] ?? null,
            'license_number'  => $data['license_number'] ?? null,
            'license_state'   => $data['license_state'] ?? null,
            'specialty'       => $data['specialty'] ?? null,
            'credentials'     => $data['credentials'] ?? null,
            'licensed_states' => $data['licensed_states'] ?? [],
        ]);

        return redirect()->route('admin.clinicians.index')->with('success', 'Clinician created.');
    }

    public function show(int $id)
    {
        $clinician = Clinician::with(['user', 'cases.patient'])->withCount('cases')->findOrFail($id);
        return view('admin.clinicians.show', compact('clinician'));
    }

    public function update(Request $request, int $id)
    {
        $clinician = Clinician::findOrFail($id);

        $data = $request->validate([
            'status'          => 'nullable|in:active,inactive,suspended',
            'is_available'    => 'nullable|boolean',
            'max_daily_cases' => 'nullable|integer|min:1',
            'specialty'       => 'nullable|string',
            'licensed_states' => 'nullable|array',
        ]);

        $clinician->update($data);

        return back()->with('success', 'Clinician updated.');
    }

    public function priorityIndex()
    {
        $clinicians = Clinician::with('user')
            ->withCount([
                'cases as active_cases_count' => fn($q) => $q->whereIn('status', [
                    PatientCase::STATUS_ASSIGNED,
                    PatientCase::STATUS_APPROVED,
                ]),
            ])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return view('admin.clinicians.priority', compact('clinicians'));
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:clinicians,id']);

        foreach ($request->ids as $rank => $id) {
            Clinician::where('id', $id)->update(['priority' => $rank]);
        }

        return response()->json(['success' => true]);
    }

    public function updateCaseLoad(Request $request, int $id)
    {
        $request->validate(['max_daily_cases' => 'required|integer|min:1|max:999']);

        Clinician::findOrFail($id)->update(['max_daily_cases' => $request->max_daily_cases]);

        return response()->json(['success' => true]);
    }
}
