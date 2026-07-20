@extends('layouts.clinician')

@section('title', 'My Cases')
@section('page-title', 'My Cases')

@section('content')
<div class="ma-surface">

    {{-- Tab strip --}}
    <div class="mc-tab-strip">
        @php
            $tabs = [
                'active'    => ['label' => 'Active',    'icon' => 'bi-activity'],
                'completed' => ['label' => 'Completed', 'icon' => 'bi-check-circle'],
                'cancelled' => ['label' => 'Cancelled', 'icon' => 'bi-x-circle'],
                'all'       => ['label' => 'All cases', 'icon' => 'bi-grid'],
            ];
        @endphp
        @foreach($tabs as $key => $meta)
        <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('tab','page'), ['tab' => $key])) }}"
           class="mc-tab {{ $tab === $key ? 'active' : '' }}">
            <i class="bi {{ $meta['icon'] }}"></i>
            {{ $meta['label'] }}
            <span class="mc-tab-count">{{ number_format($counts[$key]) }}</span>
        </a>
        @endforeach
    </div>

    {{-- Main card --}}
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="ma-eyebrow">Case history</div>
                    <div class="ma-title">
                        @if($tab === 'active')    Active cases
                        @elseif($tab === 'completed') Completed cases
                        @elseif($tab === 'cancelled') Cancelled cases
                        @else All cases
                        @endif
                    </div>
                    <div class="ma-sub">{{ number_format($counts[$tab]) }} {{ $counts[$tab] === 1 ? 'case' : 'cases' }} found.</div>
                </div>
            </div>
            <form action="{{ route('clinician.cases.my-cases') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search patient name…" value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="triage" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Triage</option>
                        @foreach(['red' => 'Red', 'yellow' => 'Yellow', 'green' => 'Green'] as $val => $lbl)
                            <option value="{{ $val }}" {{ request('triage') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    @if(request()->anyFilled(['search','triage']))
                        <a href="{{ route('clinician.cases.my-cases', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0" style="overflow:visible">
            <div class="table-responsive" style="overflow-x:auto;min-height:1px">
                <table class="table mc-case-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:4px;padding:0"></th>
                            <th>Patient</th>
                            <th>Triage</th>
                            <th>Offerings</th>
                            <th>Company</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                        @php
                            $currentClinicianId = Auth::user()->clinician?->id;
                            $isMine  = $case->clinician_id && $case->clinician_id === $currentClinicianId;
                            $isOpen  = in_array($case->status, ['waiting','assigned','support','approved','processing']);

                            // Left stripe colour
                            $stripe = match(true) {
                                $case->status === 'waiting'                         => '#0d6efd',
                                in_array($case->status, ['assigned','support'])     => '#fd7e14',
                                in_array($case->status, ['approved','processing'])  => '#0dcaf0',
                                $case->status === 'completed'                       => '#198754',
                                default                                             => '#adb5bd',
                            };

                            // Smart action button
                            if ($case->status === 'waiting') {
                                $btnLabel  = 'Claim';
                                $btnClass  = 'btn-primary';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-hand-index';
                            } elseif ($case->status === 'assigned' && $isMine) {
                                $btnLabel  = 'Review';
                                $btnClass  = 'btn-primary';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-arrow-right';
                            } elseif (in_array($case->status, ['support','approved','processing']) && $isMine) {
                                $btnLabel  = 'Review';
                                $btnClass  = 'btn-outline-primary';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-arrow-right';
                            } elseif ($case->status === 'completed') {
                                $btnLabel  = 'View';
                                $btnClass  = 'btn-outline-success';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-eye';
                            } elseif ($case->status === 'cancelled') {
                                $btnLabel  = 'View';
                                $btnClass  = 'btn-outline-secondary';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-eye';
                            } else {
                                $btnLabel  = 'View';
                                $btnClass  = 'btn-outline-secondary';
                                $btnUrl    = route('clinician.cases.show', $case->uuid);
                                $btnIcon   = 'bi-eye';
                            }
                        @endphp
                        <tr class="mc-row">
                            <td class="mc-stripe p-0" style="background:{{ $stripe }};width:4px;min-width:4px"></td>
                            <td>
                                <div class="fw-semibold">{{ $case->patient?->full_name ?? 'N/A' }}</div>
                                <div class="mc-meta">
                                    {{ strtoupper(substr($case->patient?->gender ?? '', 0, 1)) ?: '—' }}
                                    @if($case->patient?->age) · {{ $case->patient->age }} yrs @endif
                                    @if(!is_null($case->patient?->bmi)) · BMI {{ number_format($case->patient->bmi, 1) }} @endif
                                </div>
                                @if($case->unread_messages_count > 0)
                                    <span class="ma-pill accent mt-1">{{ $case->unread_messages_count }} new msg</span>
                                @endif
                            </td>
                            <td>
                                @if($isOpen)
                                    <x-triage-pill :case="$case" />
                                @else
                                    <span class="mc-triage-closed">—</span>
                                @endif
                            </td>
                            <td>
                                @foreach($case->caseOfferings->take(2) as $co)
                                    <span class="ma-pill neutral">{{ $co->offering->name ?? '?' }}</span>
                                @endforeach
                            </td>
                            <td class="text-muted small">{{ $case->partner?->name ?? '—' }}</td>
                            <td>
                                <div class="small">{{ $case->created_at->format('M d, Y') }}</div>
                                <div class="mc-meta">{{ $case->created_at->diffForHumans(null, true) }} ago</div>
                            </td>
                            <td>
                                <span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span>
                                @if($case->status === 'assigned' && $isMine)
                                    <div class="mc-meta mt-1">Assigned to you</div>
                                @elseif($case->status === 'assigned' && !$isMine)
                                    <div class="mc-meta mt-1">Other clinician</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ $btnUrl }}" class="btn btn-sm {{ $btnClass }}">
                                    <i class="bi {{ $btnIcon }} me-1"></i>{{ $btnLabel }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No {{ $tab !== 'all' ? $tab : '' }} cases found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($cases->hasPages())
        <div class="card-footer">{{ $cases->links() }}</div>
        @endif
    </div>

</div>
@endsection

@section('scripts')
<style>
/* ── Tab strip ────────────────────────────────────────────────── */
.mc-tab-strip {
    display: flex;
    gap: .25rem;
    margin-bottom: 1.25rem;
    border-bottom: 2px solid var(--ma-border, #e5e7eb);
    padding-bottom: 0;
}
.mc-tab {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1rem;
    font-size: .8rem;
    font-weight: 600;
    color: #6c757d;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    border-radius: .25rem .25rem 0 0;
    transition: color .15s, border-color .15s;
    white-space: nowrap;
}
.mc-tab:hover { color: #0d6efd; }
.mc-tab.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background: rgba(13,110,253,.04);
}
.mc-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.4rem;
    height: 1.4rem;
    padding: 0 .35rem;
    font-size: .7rem;
    font-weight: 700;
    border-radius: 999px;
    background: #e9ecef;
    color: #495057;
    line-height: 1;
}
.mc-tab.active .mc-tab-count {
    background: #dbeafe;
    color: #1d4ed8;
}

/* ── Table ────────────────────────────────────────────────────── */
.mc-case-table thead th {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #6c757d;
    border-bottom: 1px solid #dee2e6;
    padding: .6rem .75rem;
    white-space: nowrap;
}
.mc-case-table tbody .mc-row td {
    vertical-align: middle;
    padding: .75rem .75rem;
    border-bottom: 1px solid #f1f3f5;
}
.mc-case-table tbody .mc-row:last-child td { border-bottom: none; }
.mc-case-table tbody .mc-row:hover td { background: #f8fafc; }
.mc-stripe { border-radius: 3px 0 0 3px; }
.mc-meta { font-size: .72rem; color: #9ca3af; margin-top: .1rem; }
.mc-triage-closed { color: #ced4da; font-size: .85rem; }

@media (prefers-color-scheme: dark) {
    .mc-tab { color: #9ca3af; border-bottom-color: transparent; }
    .mc-tab:hover { color: #60a5fa; }
    .mc-tab.active { color: #60a5fa; border-bottom-color: #60a5fa; background: rgba(96,165,250,.06); }
    .mc-tab-count { background: #374151; color: #d1d5db; }
    .mc-tab.active .mc-tab-count { background: #1e3a5f; color: #93c5fd; }
    .mc-tab-strip { border-bottom-color: #374151; }
    .mc-case-table thead th { color: #9ca3af; border-bottom-color: #374151; }
    .mc-case-table tbody .mc-row td { border-bottom-color: #1f2937; }
    .mc-case-table tbody .mc-row:hover td { background: #1f2937; }
    .mc-meta { color: #6b7280; }
}
:root[data-theme="dark"] .mc-tab { color: #9ca3af; }
:root[data-theme="dark"] .mc-tab.active { color: #60a5fa; border-bottom-color: #60a5fa; background: rgba(96,165,250,.06); }
:root[data-theme="dark"] .mc-tab-strip { border-bottom-color: #374151; }
:root[data-theme="dark"] .mc-case-table thead th { color: #9ca3af; border-bottom-color: #374151; }
:root[data-theme="dark"] .mc-case-table tbody .mc-row td { border-bottom-color: #1f2937; }
:root[data-theme="dark"] .mc-case-table tbody .mc-row:hover td { background: #1f2937; }
:root[data-theme="light"] .mc-tab { color: #6c757d; }
:root[data-theme="light"] .mc-tab.active { color: #0d6efd; border-bottom-color: #0d6efd; }
</style>
@endsection
