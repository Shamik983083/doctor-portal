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
        // Devin msg 2117: an admin is over specific doctors and sees only those.
        $clinicians = Clinician::visibleTo($request->user())
            ->with('user')
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

        $clinician = Clinician::create([
            'user_id'         => $user->id,
            'npi'             => $data['npi'] ?? null,
            'specialty'       => $data['specialty'] ?? null,
            'credentials'     => $data['credentials'] ?? null,
            'licensed_states' => $licensedStates,
        ]);

        /*
         * A Doctor Admin who creates a doctor is put over them immediately.
         * Without this the doctor they just created would vanish from their own
         * list, which reads as a bug rather than as scoping. A super admin needs
         * no row: they see everyone regardless.
         */
        $actor = $request->user();
        if ($actor && ! $actor->isSuperAdmin()) {
            $actor->managedClinicians()->syncWithoutDetaching([$clinician->id]);
        }

        return redirect()->route('admin.clinicians.index')->with('success', 'Clinician created.');
    }

    public function show(int $id)
    {
        $clinician = Clinician::visibleTo(auth()->user())->with(['user', 'cases.patient'])->withCount('cases')->findOrFail($id);
        return view('admin.clinicians.show', compact('clinician'));
    }

    public function edit(int $id)
    {
        $clinician = Clinician::visibleTo(auth()->user())
            ->with(['user', 'acceptedCategories'])
            ->findOrFail($id);

        // The eligibility gate's doctor side (Devin msg 2308). Only active
        // categories are offered: a deactivated one should not be newly ticked,
        // though an existing tick is left alone rather than silently dropped.
        $categories = \App\Models\OfferingCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.clinicians.edit', [
            'clinician'            => $clinician,
            'categories'           => $categories,
            'acceptedCategoryIds'  => $clinician->acceptedCategories->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $clinician = Clinician::visibleTo(auth()->user())->with('user')->findOrFail($id);

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
            // Capacity controls (Devin msgs 2248/2250). Empty means uncapped, so
            // min:0 is not used; a blank field clears the cap rather than setting 0.
            'accepting_new_cases'          => 'nullable|boolean',
            'max_daily_new_cases'          => 'nullable|integer|min:1',
            'max_open_cases'               => 'nullable|integer|min:1',
            'daily_refill_alert_threshold' => 'nullable|integer|min:1',
            // The eligibility gate, doctor side (Devin msg 2308).
            'accepted_categories'   => 'nullable|array',
            'accepted_categories.*' => 'integer|exists:offering_categories,id',
            'accepts_async_visits'  => 'nullable|boolean',
            'accepts_sync_visits'   => 'nullable|boolean',
            'scheduling_link'       => 'nullable|url|max:500',
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
            // A blank number field clears the cap (null = uncapped). The checkbox
            // is read with boolean() so an unchecked box means "books full".
            'accepting_new_cases'          => $request->boolean('accepting_new_cases'),
            'max_daily_new_cases'          => $data['max_daily_new_cases'] ?? null,
            'max_open_cases'               => $data['max_open_cases'] ?? null,
            'daily_refill_alert_threshold' => $data['daily_refill_alert_threshold'] ?? null,
            'accepts_async_visits'         => $request->boolean('accepts_async_visits'),
            'accepts_sync_visits'          => $request->boolean('accepts_sync_visits'),
            // Blank clears it. A doctor who takes synchronous visits with no link
            // is blocked from those cases rather than assigned ones nobody can
            // book, which is why this is validated as a real URL.
            'scheduling_link'              => $data['scheduling_link'] ?? null,
            'licensed_states' => $licensedStates,
        ]);

        /*
         * Accepted categories (Devin msg 2308). sync() rather than attach so
         * unticking removes, and an empty array is a real answer meaning "this
         * doctor accepts nothing" rather than "leave it alone". That is the
         * fail-closed reading, matching how blank licensure now behaves.
         */
        $clinician->acceptedCategories()->sync($data['accepted_categories'] ?? []);

        return redirect()->route('admin.clinicians.show', $clinician->id)
            ->with('success', 'Clinician updated successfully.');
    }

    public function priorityIndex()
    {
        // The priority screen ranks doctors against each other, so it must show
        // only the ones this admin is over. Listing everyone would leak the full
        // roster and let an admin re-rank doctors outside their group.
        $clinicians = Clinician::visibleTo(auth()->user())
            ->with('user')
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
            Clinician::visibleTo(auth()->user())->where('id', $id)->update(['priority' => $rank]);
        }

        return response()->json(['success' => true]);
    }

    public function updateCaseLoad(Request $request, int $id)
    {
        $request->validate(['max_daily_cases' => 'required|integer|min:1|max:999']);

        Clinician::visibleTo(auth()->user())->findOrFail($id)->update(['max_daily_cases' => $request->max_daily_cases]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $id)
    {
        $clinician = Clinician::visibleTo(auth()->user())->with('user')->findOrFail($id);
        $name = $clinician->full_name;
        $clinician->delete();

        return redirect()->route('admin.clinicians.index')
            ->with('success', "Clinician {$name} has been deleted.");
    }

    /**
     * DA3: Provider workload — active case counts across all doctors in scope,
     * grouped by status, with capacity bars and a quick "Reassign" entry point.
     */
    public function workload(Request $request)
    {
        $user = $request->user();

        $clinicians = Clinician::visibleTo($user)
            ->with('user')
            ->get();

        $clinicianIds = $clinicians->pluck('id');

        // One query: active case counts per clinician per status
        $countRows = PatientCase::whereIn('clinician_id', $clinicianIds)
            ->whereIn('status', [
                PatientCase::STATUS_WAITING,
                PatientCase::STATUS_ASSIGNED,
                PatientCase::STATUS_SUPPORT,
                PatientCase::STATUS_APPROVED,
                PatientCase::STATUS_PROCESSING,
            ])
            ->selectRaw('clinician_id, status, COUNT(*) as count')
            ->groupBy('clinician_id', 'status')
            ->get();

        // Map: clinician_id => [status => count]. Initialise every ID so
        // doctors with zero active cases still appear in the table.
        $byStatus = [];
        foreach ($clinicianIds as $id) {
            $byStatus[$id] = [];
        }
        foreach ($countRows as $row) {
            $byStatus[$row->clinician_id][$row->status] = (int) $row->count;
        }

        // Completed today (status transitions happen via updated_at on completed rows)
        $completedToday = PatientCase::whereIn('clinician_id', $clinicianIds)
            ->where('status', PatientCase::STATUS_COMPLETED)
            ->whereDate('updated_at', today())
            ->selectRaw('clinician_id, COUNT(*) as cnt')
            ->groupBy('clinician_id')
            ->pluck('cnt', 'clinician_id');

        // Sort: heaviest load at the top so overloaded doctors are immediately visible
        $clinicians = $clinicians->sortByDesc(function ($c) use ($byStatus) {
            return array_sum($byStatus[$c->id] ?? []);
        })->values();

        return view('admin.clinicians.workload', compact(
            'clinicians', 'byStatus', 'completedToday', 'user'
        ));
    }

    /**
     * B4: Bulk-reassign open cases from one clinician to another.
     *
     * Only shows clinicians this admin is over.  The "from" clinician is
     * optional — leaving it blank lists ALL open cases visible to the admin.
     */
    public function bulkReassign(Request $request)
    {
        $clinicians = Clinician::visibleTo($request->user())
            ->with('user')
            ->where('status', 'active')
            ->get()
            ->sortBy(fn($c) => $c->full_name)
            ->values();

        $fromId = $request->input('from_clinician_id');
        $cases  = collect();

        if ($fromId) {
            // Validate the "from" doctor is within this admin's scope
            $from = Clinician::visibleTo($request->user())->findOrFail($fromId);

            $cases = PatientCase::where('clinician_id', $from->id)
                ->whereIn('status', [
                    PatientCase::STATUS_ASSIGNED,
                    PatientCase::STATUS_APPROVED,
                    PatientCase::STATUS_PROCESSING,
                ])
                ->with('patient')
                ->latest()
                ->get();
        }

        return view('admin.clinicians.bulk-reassign', compact('clinicians', 'cases', 'fromId'));
    }

    public function bulkReassignSubmit(Request $request)
    {
        $request->validate([
            'to_clinician_id'   => 'required|exists:clinicians,id',
            'case_ids'          => 'required|array|min:1',
            'case_ids.*'        => 'integer|exists:patient_cases,id',
        ]);

        $toClinician = Clinician::visibleTo($request->user())->findOrFail($request->to_clinician_id);

        // Guard: only move cases that originally belong to a visible clinician
        $visibleIds = Clinician::visibleTo($request->user())->pluck('id');

        $moved = PatientCase::whereIn('id', $request->case_ids)
            ->whereIn('clinician_id', $visibleIds)
            ->whereIn('status', [
                PatientCase::STATUS_ASSIGNED,
                PatientCase::STATUS_APPROVED,
                PatientCase::STATUS_PROCESSING,
            ])
            ->update([
                'clinician_id' => $toClinician->id,
                'assigned_at'  => now(),
            ]);

        return redirect()->route('admin.clinicians.bulk-reassign')
            ->with('success', "{$moved} case(s) reassigned to {$toClinician->full_name}.");
    }
}
