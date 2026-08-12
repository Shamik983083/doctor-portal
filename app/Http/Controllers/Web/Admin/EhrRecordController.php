<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EhrRecord;
use App\Models\Partner;
use App\Services\EhrRecordService;
use Illuminate\Http\Request;

class EhrRecordController extends Controller
{
    public function __construct(private EhrRecordService $ehrRecords) {}

    public function index(Request $request)
    {
        $records = EhrRecord::with('case')
            ->when($request->partner_id, fn ($q, $id) => $q->where('partner_id', $id))
            ->when($request->status,     fn ($q, $s)  => $q->where('status', $s))
            ->when($request->date,       fn ($q, $d)  => $q->whereDate('created_at', $d))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $partners = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.ehr-records.index', compact('records', 'partners'));
    }

    public function show(string $uuid)
    {
        $record = EhrRecord::where('uuid', $uuid)
            ->with(['case', 'clinicalNote'])
            ->firstOrFail();

        $maxAttempts = (int) config('ehr.max_attempts', 5);

        $canRetry = $record->status === EhrRecord::STATUS_FAILED
            && $record->attempts < $maxAttempts
            && (bool) config('ehr.enabled');

        return view('admin.ehr-records.show', compact('record', 'canRetry', 'maxAttempts'));
    }

    public function retry(string $uuid)
    {
        $record = EhrRecord::where('uuid', $uuid)->firstOrFail();

        $maxAttempts = (int) config('ehr.max_attempts', 5);

        if ($record->status !== EhrRecord::STATUS_FAILED) {
            return back()->with('error', 'Only failed records can be retried.');
        }

        if ($record->attempts >= $maxAttempts) {
            return back()->with('error', 'This record has exhausted its retry budget (' . $maxAttempts . ' attempts).');
        }

        if (! config('ehr.enabled')) {
            return back()->with('error', 'EHR push is globally disabled (EHR_ENABLED is not set).');
        }

        $updated = $this->ehrRecords->push($record);

        if ($updated->status === EhrRecord::STATUS_SENT) {
            return back()->with('success', 'EHR record pushed successfully. Reference: ' . $updated->reference);
        }

        return back()->with('error', 'Retry attempt failed: ' . ($updated->last_error ?? 'unknown error'));
    }
}
