<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $patients = $this->partner()->patients()
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->withCount('cases')
            ->latest()->paginate(20);

        return view('partner.patients.index', compact('patients'));
    }

    public function show(int $id)
    {
        $patient = $this->partner()->patients()
            ->with(['cases.caseOfferings.offering', 'orders', 'tags'])
            ->withCount('cases')
            ->findOrFail($id);

        return view('partner.patients.show', compact('patient'));
    }
}
