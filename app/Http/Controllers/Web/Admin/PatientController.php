<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\Partner;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        // Patient records are PHI. A Doctor Admin sees a patient only if that
        // patient has a case with one of their doctors (Devin msg 2117).
        $patients = Patient::visibleTo($request->user())
            ->with(['partner', 'cases' => fn ($q) => $q->with('subStorefront')->latest()])
            ->withCount('cases')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->input('partner_id'), fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25)->withQueryString();

        $partners = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.patients.index', compact('patients', 'partners'));
    }

    public function show(int $id)
    {
        // Scoped on the detail page too: a scoped list with an open detail view
        // is one guessed id away from being no protection at all.
        $patient = Patient::visibleTo(auth()->user())->with([
            'partner',
            'collaboratingClinician.user',
            'cases' => fn($q) => $q->with(['clinician.user', 'caseOfferings.offering', 'subStorefront'])->latest(),
            'orders.pharmacy', 'files', 'tags',
        ])->findOrFail($id);

        $clinicians = Clinician::with('user')
            ->where('status', 'active')
            ->get()
            ->sortBy(fn($c) => $c->full_name)
            ->values();

        return view('admin.patients.show', compact('patient', 'clinicians'));
    }

    public function updateCollaboratingClinician(Request $request, int $id)
    {
        $patient = Patient::visibleTo(auth()->user())->findOrFail($id);

        $request->validate([
            'collaborating_clinician_id' => 'nullable|exists:clinicians,id',
        ]);

        $patient->update([
            'collaborating_clinician_id' => $request->input('collaborating_clinician_id') ?: null,
        ]);

        return redirect()->route('admin.patients.show', $patient->id)
            ->with('success', 'Collaborating clinician updated.');
    }

    public function destroy(int $id)
    {
        $patient = Patient::visibleTo(auth()->user())->findOrFail($id);
        $patient->delete();

        return redirect()->route('admin.patients.index')
            ->with('success', "Patient {$patient->full_name} has been deleted.");
    }
}
