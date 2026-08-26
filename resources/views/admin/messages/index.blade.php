@extends('layouts.admin')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@unless($user->isSuperAdmin())
<div class="alert alert-info py-2 px-3 small mb-3">
    <i class="bi bi-person-lock me-1"></i>
    Showing conversations on cases assigned to <strong>your doctors only</strong>.
</div>
@endunless

{{-- Tab bar --}}
<ul class="nav nav-tabs mb-0" style="border-bottom:none;">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'patient' ? 'active' : '' }}"
           href="{{ route('admin.messages.index', ['tab' => 'patient']) }}">
            <i class="bi bi-person me-1"></i>Patient Conversations
            @if($patientUnread > 0)
                <span class="badge bg-primary ms-1" style="font-size:.6rem;">{{ $patientUnread }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'provider' ? 'active' : '' }}"
           href="{{ route('admin.messages.index', ['tab' => 'provider']) }}">
            <i class="bi bi-person-badge me-1"></i>Provider Messages
            @if($providerCaseCount > 0)
                <span class="badge bg-secondary ms-1" style="font-size:.6rem;">{{ $providerCaseCount }}</span>
            @endif
        </a>
    </li>
</ul>

@if($cases->isEmpty())
{{-- ── Empty state ──────────────────────────────────────────────── --}}
<div class="card" style="border-top-left-radius:0;">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-chat-square-text fs-2 d-block mb-2 opacity-25"></i>
        @if($tab === 'patient')
            No patient conversations found in your scope yet.
        @else
            No internal provider messages yet. Open a case and use
            <strong>Message Clinician</strong> to start a thread.
        @endif
    </div>
</div>

@else
{{-- ── Two-pane layout ──────────────────────────────────────────── --}}
<div class="card" style="border-top-left-radius:0;">
    <div class="row g-0" style="height:calc(100vh - 220px); min-height:520px;">

        {{-- Left: conversation list --}}
        <div class="col-4 border-end d-flex flex-column" style="overflow:hidden;">

            {{-- List scroll area --}}
            <div style="overflow-y:auto; flex:1;">
                @foreach($cases as $case)
                @php
                    $preview  = $latestByCase->get($case->id);
                    $isActive = $selected && $selected->id === $case->id;
                    $unread   = (int)($case->unread_count ?? 0);
                @endphp
                <a href="{{ route('admin.messages.index', ['tab' => $tab, 'case' => $case->uuid]) }}"
                   class="d-flex align-items-start gap-2 px-3 py-2 text-decoration-none border-bottom {{ $isActive ? 'bg-primary bg-opacity-10' : '' }}"
                   style="transition:background .12s;">

                    {{-- Avatar --}}
                    @php
                        $pname = trim(($case->patient?->first_name ?? '') . ' ' . ($case->patient?->last_name ?? ''));
                        $initials = strtoupper(collect(explode(' ', $pname))->map(fn($p) => mb_substr($p,0,1))->take(2)->implode(''));
                    @endphp
                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-semibold text-white"
                         style="width:36px;height:36px;font-size:.7rem;background:{{ $tab === 'patient' ? '#4361ee' : '#6f42c1' }};margin-top:2px;">
                        {{ $initials ?: '?' }}
                    </div>

                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="fw-semibold text-dark" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;">
                                {{ $pname ?: 'Unknown Patient' }}
                            </span>
                            <span class="text-muted flex-shrink-0" style="font-size:.68rem;">
                                {{ $case->last_message_at ? \Carbon\Carbon::parse($case->last_message_at)->diffForHumans(null,true) : '' }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-muted" style="font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;">
                                @if($preview)
                                    {{ \Illuminate\Support\Str::limit($preview->body, 48) }}
                                @else
                                    {{ $case->partner?->name ?? '' }}
                                @endif
                            </span>
                            @if($unread > 0)
                                <span class="badge bg-primary rounded-pill flex-shrink-0" style="font-size:.6rem;">{{ $unread }}</span>
                            @endif
                        </div>
                        @if($tab === 'provider')
                            <div class="text-muted mt-1" style="font-size:.68rem;">
                                <i class="bi bi-person-badge me-1"></i>{{ $case->clinician?->full_name ?? 'Unassigned' }}
                            </div>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($cases->hasPages())
            <div class="border-top px-3 py-2" style="font-size:.78rem;">
                {{ $cases->links('pagination::simple-bootstrap-5') }}
            </div>
            @endif
        </div>

        {{-- Right: thread + compose --}}
        <div class="col-8 d-flex flex-column" style="overflow:hidden;">

            @if($selected)
            {{-- Thread header --}}
            @php
                $pname = trim(($selected->patient?->first_name ?? '') . ' ' . ($selected->patient?->last_name ?? ''));
            @endphp
            <div class="d-flex align-items-center gap-2 px-4 py-3 border-bottom bg-white">
                <div>
                    <div class="fw-semibold" style="font-size:.9rem;">{{ $pname ?: 'Unknown Patient' }}</div>
                    <div class="text-muted" style="font-size:.72rem;">
                        {{ $selected->partner?->name ?? '' }}
                        @if($tab === 'provider')
                            &middot; {{ $selected->clinician?->full_name ?? 'Unassigned' }}
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.cases.show', $selected->uuid) }}"
                   class="btn btn-sm btn-outline-secondary py-0 px-2 ms-auto" style="font-size:.75rem;">
                    Open case <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            {{-- Thread scroll --}}
            <div id="msgThread" style="flex:1;overflow-y:auto;padding:16px 20px;background:#f8f9fc;">
                @php $prevDate = null; @endphp
                @forelse($thread as $msg)
                @php
                    $msgDate   = $msg->created_at->format('Y-m-d');
                    $isAdmin   = $msg->sender_type === 'admin';
                    $isClinic  = $msg->sender_type === 'clinician';
                    $isPatient = $msg->sender_type === 'patient';
                    $isInternal= $msg->channel === 'internal';

                    if ($isAdmin) {
                        $avatarBg  = '#6f42c1'; $avatarTxt = '#fff';
                        $senderName = $msg->user?->name ?? 'Admin';
                        $alignRight = true;
                    } elseif ($isClinic) {
                        $avatarBg  = '#4361ee'; $avatarTxt = '#fff';
                        $senderName = $msg->clinician?->user?->name ?? 'Clinician';
                        $alignRight = false;
                    } elseif ($isPatient) {
                        $avatarBg  = '#2dc653'; $avatarTxt = '#fff';
                        $senderName = $pname ?: 'Patient';
                        $alignRight = false;
                    } else {
                        $avatarBg  = '#6c757d'; $avatarTxt = '#fff';
                        $senderName = ucfirst($msg->sender_type ?? 'System');
                        $alignRight = false;
                    }

                    $initials = strtoupper(collect(explode(' ', trim($senderName)))->map(fn($p) => mb_substr($p,0,1))->take(2)->implode(''));
                @endphp

                {{-- Date separator --}}
                @if($msgDate !== $prevDate)
                @php $prevDate = $msgDate; @endphp
                <div class="d-flex align-items-center gap-2 my-3">
                    <hr class="flex-grow-1 my-0" style="border-color:#dee2e6;">
                    <span style="font-size:.68rem;color:#adb5bd;white-space:nowrap;text-transform:uppercase;letter-spacing:.04em;font-weight:500;">
                        {{ $msg->created_at->isToday() ? 'Today' : ($msg->created_at->isYesterday() ? 'Yesterday' : $msg->created_at->format('M j, Y')) }}
                    </span>
                    <hr class="flex-grow-1 my-0" style="border-color:#dee2e6;">
                </div>
                @endif

                {{-- Message row --}}
                <div class="d-flex align-items-end gap-2 mb-3 {{ $alignRight ? 'flex-row-reverse' : '' }}">

                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-semibold"
                         style="width:32px;height:32px;font-size:.65rem;background:{{ $avatarBg }};color:{{ $avatarTxt }};">
                        {{ $initials ?: '?' }}
                    </div>

                    <div style="max-width:65%;">
                        <div class="d-flex align-items-baseline gap-1 mb-1 {{ $alignRight ? 'justify-content-end' : '' }}">
                            <span style="font-size:.7rem;font-weight:600;color:#495057;">{{ $senderName }}</span>
                            <span style="font-size:.65rem;color:#adb5bd;">{{ $msg->created_at->format('H:i') }}</span>
                            @if($isInternal)
                                <span style="font-size:.62rem;color:#6f42c1;font-weight:500;">internal</span>
                            @endif
                        </div>

                        @if($isAdmin)
                        <div style="background:#6f42c1;color:#fff;padding:9px 13px;border-radius:{{ $alignRight ? '14px 3px 14px 14px' : '3px 14px 14px 14px' }};font-size:.85rem;line-height:1.5;word-break:break-word;box-shadow:0 2px 6px rgba(111,66,193,.2);">
                            {{ $msg->body }}
                        </div>
                        @elseif($isClinic)
                        <div style="background:#4361ee;color:#fff;padding:9px 13px;border-radius:{{ $alignRight ? '14px 3px 14px 14px' : '3px 14px 14px 14px' }};font-size:.85rem;line-height:1.5;word-break:break-word;box-shadow:0 2px 6px rgba(67,97,238,.2);">
                            {{ $msg->body }}
                        </div>
                        @elseif($isPatient)
                        <div style="background:#fff;color:#212529;padding:9px 13px;border-radius:3px 14px 14px 14px;border:1px solid #e9ecef;font-size:.85rem;line-height:1.5;word-break:break-word;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                            {{ $msg->body }}
                        </div>
                        @else
                        <div style="background:#f1f3f5;color:#495057;padding:8px 12px;border-radius:8px;border:1px dashed #dee2e6;font-size:.8rem;line-height:1.5;word-break:break-word;">
                            {{ $msg->body }}
                        </div>
                        @endif
                    </div>
                </div>

                @empty
                <div class="text-center text-muted py-5 small">
                    @if($tab === 'patient')
                        No messages on this case yet.
                    @else
                        No internal messages yet. Use the form below to write to the clinician.
                    @endif
                </div>
                @endforelse
            </div>

            {{-- Compose (provider tab only) --}}
            @if($tab === 'provider')
            <div class="border-top bg-white px-4 py-3">
                @if($selected->clinician_id)
                <form method="POST" action="{{ route('admin.messages.send') }}" class="d-flex gap-2 align-items-end">
                    @csrf
                    <input type="hidden" name="case_uuid" value="{{ $selected->uuid }}">
                    <div class="flex-grow-1">
                        <label class="form-label small mb-1" style="font-size:.72rem;color:#6c757d;">
                            Message to <strong>{{ $selected->clinician->full_name }}</strong> (internal — not visible to patient)
                        </label>
                        <textarea name="body" rows="2" required maxlength="5000"
                                  class="form-control form-control-sm"
                                  placeholder="Write an internal note to the clinician…"
                                  style="resize:none;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary px-3 flex-shrink-0" style="height:fit-content;">
                        <i class="bi bi-send me-1"></i>Send
                    </button>
                </form>
                @else
                <div class="text-muted small py-1">
                    <i class="bi bi-info-circle me-1"></i>
                    No clinician is assigned to this case. <a href="{{ route('admin.cases.show', $selected->uuid) }}">Assign one</a> before sending an internal message.
                </div>
                @endif
            </div>
            @endif

            @else
            {{-- No case selected (edge case: paginator moved and ?case= no longer matches) --}}
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <div class="text-center">
                    <i class="bi bi-chat-square-text fs-2 d-block mb-2 opacity-25"></i>
                    <div class="small">Select a conversation from the list</div>
                </div>
            </div>
            @endif

        </div>{{-- /col-8 --}}
    </div>{{-- /row --}}
</div>

@endif

@endsection

@section('scripts')
<script>
(function () {
    // Scroll message thread to bottom on load so the latest message is visible.
    var thread = document.getElementById('msgThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
})();
</script>
@endsection
