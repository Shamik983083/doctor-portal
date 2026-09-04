@extends('layouts.admin')

@section('title', 'Routing Exceptions')

@section('content')
{{--
    Cases that did not route (Devin msg 2308: "NO SILENT FAILURES").

    Two lists, deliberately kept apart. Exceptions are cases nobody could take and
    something is wrong. The pool queue is cases waiting to be claimed, which under
    PROVIDER_POOL is the design working. Merging them would make "broken" and
    "working" look the same, which is how a screen like this stops being read.
--}}
<style>
.rt-page { max-width: 960px; }
.rt-section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.rt-section-header { padding:14px 20px; border-bottom:1px solid #f1f3f5; display:flex; align-items:center; justify-content:space-between; }
.rt-section-header .title { font-size:.78rem; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:#6b7280; }
.rt-section-header .count { font-size:.78rem; font-weight:600; color:#374151; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:20px; padding:2px 10px; }
.rt-table { width:100%; border-collapse:collapse; }
.rt-table th { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:#9ca3af; font-weight:600; padding:10px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
.rt-table td { font-size:.82rem; padding:11px 16px; border-bottom:1px solid #f3f4f6; color:#374151; vertical-align:middle; }
.rt-table tbody tr:last-child td { border-bottom:none; }
.rt-table tbody tr.systemic td { background:#fff5f5; }
.rt-table .case-link { font-family:monospace; font-size:.82rem; color:#4f46e5; text-decoration:none; font-weight:500; }
.rt-table .case-link:hover { text-decoration:underline; }
.rt-table .partner-name { font-size:.73rem; color:#9ca3af; margin-top:2px; }
.rt-table .reason-main { font-weight:500; color:#1f2937; }
.rt-table .reason-sub { font-size:.73rem; color:#6b7280; margin-top:3px; }
.stuck-normal { color:#374151; }
.stuck-warn { color:#dc2626; font-weight:600; }
.systemic-badge { display:inline-block; font-size:.67rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:4px; padding:1px 6px; margin-top:4px; }
.clear-btn { font-size:.75rem; font-weight:500; padding:4px 12px; border-radius:7px; border:1px solid #d1d5db; background:#fff; color:#374151; cursor:pointer; transition:background .12s; }
.clear-btn:hover { background:#f3f4f6; }
.rt-empty { padding:24px 20px; font-size:.82rem; color:#9ca3af; }
.rt-footer { padding:11px 20px; border-top:1px solid #f1f3f5; font-size:.73rem; color:#9ca3af; }
.ok-callout { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 18px; font-size:.82rem; color:#166534; margin-bottom:16px; }
.waiting-orange { color:#d97706; font-weight:600; }
</style>

<div class="rt-page">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-semibold mb-1" style="font-size:1.2rem;color:#111827;letter-spacing:-.02em">Routing Exceptions</h4>
            <p class="mb-0" style="font-size:.82rem;color:#6b7280">Cases that could not be assigned, with the reason each one is stuck. An exception clears itself the moment its case routes.</p>
        </div>
        <a href="{{ route('admin.routing.pull-requests') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;border-radius:8px">
            <i class="bi bi-inbox me-1"></i>Pull requests
        </a>
    </div>

    {{-- Exceptions --}}
    @if($exceptions->isEmpty())
        <div class="ok-callout mb-4">
            <i class="bi bi-check-circle me-2"></i><strong>Nothing is stuck.</strong>
            Every case has either been assigned or is waiting in the pool below.
        </div>
    @else
        <div class="rt-section mb-4">
            <div class="rt-section-header">
                <span class="title">Stuck — needs attention</span>
                <span class="count">{{ $exceptions->count() }} {{ Str::plural('case', $exceptions->count()) }}</span>
            </div>
            <table class="rt-table">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>State</th>
                        <th>Why</th>
                        <th>Stuck for</th>
                        <th>Tries</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($exceptions as $exception)
                    <tr class="{{ $exception->isSystemic() ? 'systemic' : '' }}">
                        <td>
                            @if($exception->case)
                                <a class="case-link" href="{{ route('admin.cases.show', $exception->case->uuid) }}">
                                    {{ Str::limit($exception->case->uuid, 8, '') }}
                                </a>
                                <div class="partner-name">{{ $exception->case->partner?->name }}{{ $exception->case->subStorefront ? ' · ' . $exception->case->subStorefront->name : '' }}</div>
                            @else
                                <span style="color:#9ca3af;font-size:.78rem">case removed</span>
                            @endif
                        </td>
                        <td>{{ $exception->case?->patient_state ?? '—' }}</td>
                        <td>
                            <div class="reason-main">{{ $exception->reasonLabel() }}</div>
                            @if($exception->isSystemic())
                                <div><span class="systemic-badge">Affects every case</span></div>
                            @endif
                            @if($exception->provider_reasons)
                                @php $codes = collect($exception->provider_reasons)->flatten()->countBy(); @endphp
                                <div class="reason-sub">
                                    @foreach($codes as $code => $count)
                                        {{ \App\Services\Routing\EligibilityEvaluator::REASON_LABELS[$code] ?? $code }} ({{ $count }}){{ !$loop->last ? ' · ' : '' }}
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="{{ $exception->ageHours() >= 24 ? 'stuck-warn' : 'stuck-normal' }}">
                                {{ $exception->first_seen_at?->diffForHumans(null, true) }}
                            </span>
                        </td>
                        <td>{{ $exception->occurrences }}</td>
                        <td style="text-align:right">
                            <form method="POST" action="{{ route('admin.routing.exceptions.resolve', $exception->id) }}">
                                @csrf
                                <button class="clear-btn">Clear</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Pool queue --}}
    <div class="rt-section">
        <div class="rt-section-header">
            <span class="title">Unclaimed pool queue</span>
            <span class="count">{{ $pooledTotal }} {{ Str::plural('case', $pooledTotal) }} waiting</span>
        </div>
        @if($pooled->isEmpty())
            <div class="rt-empty">Nothing waiting in the pool.</div>
        @else
            <table class="rt-table">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>State</th>
                        <th>Partner</th>
                        <th>Sub-storefront</th>
                        <th>Waiting</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pooled as $case)
                    <tr>
                        <td>
                            <a class="case-link" href="{{ route('admin.cases.show', $case->uuid) }}">
                                {{ Str::limit($case->uuid, 8, '') }}
                            </a>
                        </td>
                        <td>{{ $case->patient_state ?? '—' }}</td>
                        <td>{{ $case->partner?->name }}</td>
                        <td>{{ $case->subStorefront?->name ?? '—' }}</td>
                        <td>
                            <span class="{{ $case->created_at->diffInHours(now()) >= 48 ? 'stuck-warn' : ($case->created_at->diffInHours(now()) >= 24 ? 'waiting-orange' : '') }}">
                                {{ $case->created_at->diffForHumans(null, true) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if($pooledTotal > $pooled->count())
                <div class="rt-footer">
                    Showing the {{ $pooled->count() }} oldest of {{ $pooledTotal }} total.
                    Not errors — under provider pool mode this is how cases wait.
                </div>
            @else
                <div class="rt-footer">Not errors — under provider pool mode this is how cases wait. Ordered oldest first.</div>
            @endif
        @endif
    </div>

</div>
@endsection
