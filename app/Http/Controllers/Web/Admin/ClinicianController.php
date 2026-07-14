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
            'name'                      => 'required|string',
            'email'                     => 'required|email|unique:users',
            'password'                  => 'required|min:8|confirmed',
            'npi'                       => 'required|string',
            'specialty'                 => 'nullable|string',
            'credentials'               => 'required|in:MD,DO,NP,PA',
            'license_info'              => 'required|array|min:1',
            'license_info.*.state'      => 'required|string|size:2',
            'license_info.*.number'     => 'required|string|max:100',
            'license_info.*.expiry'     => 'required|date',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole('clinician');

        $licensedStates = [];
        foreach ($request->input('license_info', []) as $abbr => $info) {
            $licensedStates[] = [
                'state'          => strtoupper($abbr),
                'license_number' => $info['number'],
                'expiry_date'    => $info['expiry'],
            ];
        }

        Clinician::create([
            'user_id'         => $user->id,
            'npi'             => $data['npi'] ?? null,
            'specialty'       => $data['specialty'] ?? null,
            'credentials'     => $data['credentials'] ?? null,
            'licensed_states' => $licensedStates,
        ]);

        return redirect()->route('admin.clinicians.index')->with('success', 'Clinician created.');
    }

    public function show(int $id)
    {
        $clinician = Clinician::with(['user', 'cases.patient'])->withCount('cases')->findOrFail($id);
        return view('admin.clinicians.show', compact('clinician'));
    }

    public function edit(int $id)
    {
        $clinician = Clinician::with('user')->findOrFail($id);
        return view('admin.clinicians.edit', compact('clinician'));
    }

    public function update(Request $request, int $id)
    {
        $clinician = Clinician::with('user')->findOrFail($id);

        $data = $request->validate([
            'name'                 => 'required|string',
            'email'                => 'required|email|unique:users,email,' . $clinician->user_id,
            'password'             => 'nullable|min:8|confirmed',
            'npi'                  => 'required|string',
            'specialty'            => 'nullable|string',
            'credentials'          => 'required|in:MD,DO,NP,PA',
            'status'               => 'required|in:active,inactive,suspended',
            'is_available'         => 'nullable|boolean',
            'max_daily_cases'      => 'nullable|integer|min:1',
            'license_info'         => 'required|array|min:1',
            'license_info.*.state' => 'required|string|size:2',
            'license_info.*.number'=> 'required|string|max:100',
            'license_info.*.expiry'=> 'required|date',
        ]);

        $userUpdate = ['name' => $data['name'], 'email' => $data['email']];
        if (!empty($data['password'])) {
            $userUpdate['password'] = Hash::make($data['password']);
        }
        $clinician->user->update($userUpdate);

        $licensedStates = [];
        foreach ($request->input('license_info', []) as $abbr => $info) {
            $licensedStates[] = [
                'state'          => strtoupper($abbr),
                'license_number' => $info['number'],
                'expiry_date'    => $info['expiry'],
            ];
        }

        $clinician->update([
            'npi'             => $data['npi'],
            'specialty'       => $data['specialty'] ?? null,
            'credentials'     => $data['credentials'],
            'status'          => $data['status'],
            'is_available'    => $request->boolean('is_available'),
            'max_daily_cases' => $data['max_daily_cases'] ?? $clinician->max_daily_cases,
            'licensed_states' => $licensedStates,
        ]);

        return redirect()->route('admin.clinicians.show', $clinician->id)
            ->with('success', 'Clinician updated successfully.');
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

    public function destroy(int $id)
    {
        $clinician = Clinician::with('user')->findOrFail($id);
        $name = $clinician->full_name;
        $clinician->delete();

        return redirect()->route('admin.clinicians.index')
            ->with('success', "Clinician {$name} has been deleted.");
    }
}
