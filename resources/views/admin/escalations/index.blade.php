@extends('layouts.admin')

@section('title', 'Escalations')
@section('page-title', 'Escalations')

@section('content')

@php
    // Human-readable labels and colours per escalation_target value
    $targetMeta = [
        'doctor_admin'    => ['label' => 'Doctor Admin',     'class' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'],
        'support'         => ['label' => 'Support Team',     'class' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25'],
        'client_response' => ['label' => 'Client Response',  'class' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25'],
        'unknown'         => ['label' => 'Unclassified',     'class' => 'bg-secondary bg-opacity-10 text-secondary border'],
    ];
@endphp

{{-- Scope note for Doctor Admin --}}
@unless($user->isSuperAdmin())
<div class="alert alert-info py-2 px-3 small mb-3">
    <i class="bi bi-person-lock me-1"></i>
    Showing escalations for cases assigned to <strong>your doctors only</strong>.
    Platform-wide escalations are visible to Super Admins.
</div>
@endunless

{{-- Type breakdown strip --}}
@if($allCount > 0)
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($targetMeta as $key => $meta)
        @php $n = (int)($byTarget[$key] ?? 0); @endphp
        @if($n > 0)
        <span class="badge {{ $meta['class'] }}" style="font-size:.78rem;font-weight:500;">
            {{ $meta['label'] }}: {{ $n }}
        </span>
        @endif
    @endforeach
    <span class="text-muted small ms-auto align-self-center">{{ $allCount }} total open</span>
</div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs mb-0" style="border-bottom:none;">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'directed' ? 'active' : '' }}"
           href="{{ route('admin.escalations.index', ['tab' => 'directed']) }}">
            <i class="bi bi-person-exclamation me-1"></i>
            Directed to Admin
            @if($directedCount > 0)
                <span class="badge bg-danger ms-1" style="font-size:.6rem;">{{ $directedCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'client_response' ? 'active' : '' }}"
           href="{{ route('admin.escalations.index', ['tab' => 'client_response']) }}">
            <i class="bi bi-chat-dots me-1"></i>
            Client Response Pending
            @if($clientResponseCount > 0)
                <span class="badge bg-info text-dark ms-1" style="font-size:.6rem;">{{ $clientResponseCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}"
           href="{{ route('admin.escalations.index', ['tab' => 'all']) }}">
            <i class="bi bi-list-check me-1"></i>
            All Escalations
            @if($allCount > 0)
                <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">{{ $allCount }}</span>
            @endif
        </a>
    </li>
</ul>

<div class="card" style="border-top-left-radius:0;">

    @if($cases->isEmpty())
        {{-- Per-tab empty states --}}
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-check-circle fs-2 d-block mb-2 opacity-25"></i>
            @if($tab === 'directed')
                @if($user->isSuperAdmin())
                    No cases are currently escalated to a Doctor Admin.
                @else
                    No cases from your doctors are awaiting your review.
                    @if($allCount > 0)
                        <div class="mt-2">
                            There {{ $allCount === 1 ? 'is' : 'are' }} {{ $allCount }} other escalation{{ $allCount === 1 ? '' : 's' }} in your group on the
                            <a href="{{ route('admin.escalations.index', ['tab' => 'all']) }}">All tab</a>.
                        </div>
                    @endif
                @endif
            @elseif($tab === 'client_response')
                No cases are currently waiting on a client or patient response.
            @else
                {{-- All tab is empty --}}
                @if($user->isSuperAdmin())
                    No open escalations across the platform.
                @else
                    No escalated cases from your doctors at the moment.
                @endif
            @endif
        </div>

    @else

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:160px;">Patient</th>
                            <th style="min-width:140px;">Clinician</th>
                            <th>Type</th>
                            <th style="min-width:200px;">Note</th>
                            <th>Partner</th>
                            <th title="Time since escalation">Age</th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($cases as $case)
                    @php
                        // support_at may be null on old cases (pre-column migration);
                        // fall back to updated_at which records the last status change.
                        $escalatedAt = $case->support_at ?? $case->updated_at;
                        $targetKey   = $case->escalation_target ?? 'unknown';
                        $meta        = $targetMeta[$targetKey] ?? $targetMeta['unknown'];
                        $noteText    = trim((string)($case->support_note ?: $case->escalation_reason));
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                {{ $case->patient?->first_name }} {{ $case->patient?->last_name }}
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                {{ $case->patient?->state ?? '—' }}
                            </div>
                        </td>

                        <td>
                            @if($case->clinician)
                                <div>{{ $case->clinician->full_name }}</div>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>

                        <td>
                            <span class="badge {{ $meta['class'] }}" style="font-size:.7rem;">
                                {{ $meta['label'] }}
                            </span>
                        </td>

                        <td>
                            @if($noteText)
                                <span title="{{ $noteText }}">
                                    {{ \Illuminate\Support\Str::limit($noteText, 80) }}
                                </span>
                            @else
                                <span class="text-muted fst-italic">(no note)</span>
                            @endif
                        </td>

                        <td class="text-muted small">
                            {{ $case->partner?->name ?? '—' }}
                        </td>

                        <td class="text-muted small" title="{{ $escalatedAt?->format('d M Y H:i') }}">
                            @if($escalatedAt)
                                {{ $escalatedAt->diffForHumans(['short' => true]) }}
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            <a href="{{ route('admin.cases.show', $case->uuid) }}"
                               class="btn btn-sm btn-outline-secondary py-0 px-2">
                                View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($cases->hasPages())
        <div class="card-footer">
            {{ $cases->links() }}
        </div>
        @endif

    @endif

</div>

{{-- Help text at the bottom --}}
<div class="text-muted small mt-3">
    <strong>Directed to Admin</strong> — cases where a clinician has explicitly escalated for physician-admin review (complex clinical decisions, coverage questions).
    <strong>Client Response Pending</strong> — cases on hold awaiting a reply from the patient or partner.
    <strong>All</strong> — every case currently paused in escalation, including general support and client-response holds.
    To resolve an escalation, open the case and assign or reassign it.
</div>

@endsection
