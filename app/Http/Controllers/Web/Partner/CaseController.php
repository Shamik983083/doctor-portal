<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $cases = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);

        return view('partner.cases.index', compact('cases'));
    }

    public function show(string $uuid)
    {
        $case = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->with(['patient', 'clinician.user', 'caseOfferings.offering',
                    'caseQuestions', 'diseases', 'orders.pharmacy',
                    'clinicalNotes', 'messages', 'files', 'events'])
            ->where('uuid', $uuid)->firstOrFail();

        return view('partner.cases.show', compact('case'));
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $case = $this->partner()->cases()->where('uuid', $uuid)->firstOrFail();

        $this->stateMachine->cancel($case, $request->input('reason', ''), null, 'partner');

        return redirect()->route('partner.cases.index')
            ->with('success', 'Case cancelled.');
    }

    public function processing(string $uuid)
    {
        $case = $this->partner()->cases()->where('uuid', $uuid)->firstOrFail();

        $this->stateMachine->startProcessing($case);

        return back()->with('success', 'Case moved to processing.');
    }
}
