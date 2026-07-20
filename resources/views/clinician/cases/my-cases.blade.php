@extends('layouts.clinician')

@section('title', 'My Cases')
@section('page-title', 'My Cases')

@section('content')
<div class="ma-surface">

    {{-- Tab cards --}}
    @php
        $tabs = [
            'active'    => ['label' => 'Active',    'icon' => 'bi-activity',      'color' => '#0d6efd', 'bg' => '#eff6ff'],
            'completed' => ['label' => 'Completed', 'icon' => 'bi-check-circle',  'color' => '#198754', 'bg' => '#f0fdf4'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => 'bi-x-circle',      'color' => '#6c757d', 'bg' => '#f8f9fa'],
            'all'       => ['label' => 'All cases', 'icon' => 'bi-grid',          'color' => '#00897b', 'bg' => '#f0fdf9'],
        ];
    @endphp
    <div class="mc-tab-strip">
        @foreach($tabs as $key => $meta)
        @php $isActive = $tab === $key; @endphp
        <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('tab','page'), ['tab' => $key])) }}"
           class="mc-tab-card {{ $isActive ? 'active' : '' }}"
           style="border-left-color:{{ $meta['color'] }};{{ $isActive ? 'background:'.$meta['bg'].';' : '' }}">
            <div class="mc-tab-card-icon" style="color:{{ $meta['color'] }}"><i class="bi {{ $meta['icon'] }}"></i></div>
            <div class="mc-tab-card-label">{{ $meta['label'] }}</div>
            <div class="mc-tab-card-count" style="color:{{ $isActive ? $meta['color'] : '#343a40' }}">{{ number_format($counts[$key]) }}</div>
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
/* ── Tab cards ────────────────────────────────────────────────── */
.mc-tab-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .85rem;
    margin-bottom: 1.25rem;
}
.mc-tab-card {
    display: flex;
    flex-direction: column;
    padding: .85rem 1rem;
    background: var(--ma-surface, #fff);
    border: 1px solid var(--ma-border, #e5e7eb);
    border-left: 4px solid transparent;
    border-radius: var(--ma-radius, .5rem);
    box-shadow: var(--ma-shadow, 0 1px 3px rgba(0,0,0,.06));
    text-decoration: none;
    color: inherit;
    transition: box-shadow .15s, transform .1s;
}
.mc-tab-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-1px); }
.mc-tab-card.active { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.mc-tab-card-icon { font-size: .95rem; margin-bottom: .35rem; opacity: .7; }
.mc-tab-card-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6c757d;
    margin-bottom: .2rem;
}
.mc-tab-card-count { font-size: 1.6rem; font-weight: 700; line-height: 1.15; }

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
    .mc-tab-card { background: #1f2937; border-color: #374151; }
    .mc-tab-card-label { color: #9ca3af; }
    .mc-tab-card-count { color: #f3f4f6; }
    .mc-case-table thead th { color: #9ca3af; border-bottom-color: #374151; }
    .mc-case-table tbody .mc-row td { border-bottom-color: #1f2937; }
    .mc-case-table tbody .mc-row:hover td { background: #1f2937; }
    .mc-meta { color: #6b7280; }
}
:root[data-theme="dark"] .mc-tab-card { background: #1f2937; border-color: #374151; }
:root[data-theme="dark"] .mc-tab-card-label { color: #9ca3af; }
:root[data-theme="dark"] .mc-case-table thead th { color: #9ca3af; border-bottom-color: #374151; }
:root[data-theme="dark"] .mc-case-table tbody .mc-row td { border-bottom-color: #1f2937; }
:root[data-theme="dark"] .mc-case-table tbody .mc-row:hover td { background: #1f2937; }
:root[data-theme="light"] .mc-tab-card { background: #fff; }
</style>
@endsection
