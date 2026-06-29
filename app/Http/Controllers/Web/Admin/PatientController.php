<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Partner;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $patients = Patient::with('partner')
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
        $patient = Patient::with([
            'partner',
            'cases' => fn($q) => $q->with(['clinician.user', 'caseOfferings.offering'])->latest(),
            'orders.pharmacy', 'files', 'tags',
        ])->findOrFail($id);

        return view('admin.patients.show', compact('patient'));
    }
}
