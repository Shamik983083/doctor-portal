@extends('layouts.admin')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')

<style>
/* ── Messages page ──────────────────────────────────────────────────── */
.msg-card {
    border: 1px solid #e2e8f0;
    border-top-left-radius: 0;
    border-radius: 0 10px 10px 10px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
    display: flex;
    flex-direction: column;
}
.msg-body {
    display: flex;
    flex-direction: row;
    overflow: hidden;
    min-height: 520px;
}

/* ── Left rail ──────────────────────────────────────────────────────── */
.msg-rail {
    width: 300px;
    flex-shrink: 0;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f8fafc;
}
.msg-rail-hdr {
    padding: 13px 16px 10px;
    border-bottom: 1px solid #edf0f5;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.msg-rail-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #64748b;
}
.msg-rail-count {
    font-size: .7rem;
    font-weight: 500;
    color: #94a3b8;
    background: #eef2ff;
    border-radius: 10px;
    padding: 1px 7px;
}

/* scrollable list */
.msg-list {
    flex: 1;
    overflow-y: scroll;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.msg-list::-webkit-scrollbar          { width: 5px; }
.msg-list::-webkit-scrollbar-track    { background: transparent; }
.msg-list::-webkit-scrollbar-thumb    { background: #cbd5e1; border-radius: 3px; }

/* conversation row */
.msg-item {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 11px 16px;
    border-bottom: 1px solid #edf0f5;
    text-decoration: none;
    cursor: pointer;
    position: relative;
    transition: background .1s;
}
.msg-item:hover { background: #f1f5f9; }
.msg-item.is-active {
    background: #eef2ff;
    border-left: 3px solid #4361ee;
    padding-left: 13px;
}
.msg-avatar-sm {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 700; color: #fff;
    flex-shrink: 0; margin-top: 1px;
}
.msg-item-content { flex: 1; min-width: 0; }
.msg-item-row1 {
    display: flex; justify-content: space-between; align-items: baseline; gap: 6px;
    margin-bottom: 3px;
}
.msg-item-name {
    font-size: .84rem; font-weight: 600; color: #1e293b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.msg-item.is-active .msg-item-name { color: #1e3a8a; }
.msg-item-time { font-size: .67rem; color: #94a3b8; flex-shrink: 0; white-space: nowrap; }
.msg-item-row2 {
    display: flex; justify-content: space-between; align-items: center; gap: 6px;
}
.msg-item-preview {
    font-size: .74rem; color: #64748b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.msg-item-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
    background: #4361ee; color: #fff;
    font-size: .6rem; font-weight: 700; flex-shrink: 0;
}
.msg-item-sub { font-size: .68rem; color: #94a3b8; margin-top: 3px; }

.msg-rail-footer {
    border-top: 1px solid #e2e8f0;
    padding: 8px 16px;
    background: #f8fafc;
    flex-shrink: 0;
    font-size: .76rem;
}

/* ── Right pane ─────────────────────────────────────────────────────── */
.msg-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

/* Thread header */
.msg-pane-hdr {
    padding: 13px 22px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
    min-height: 60px;
}
.msg-pane-hdr-name {
    font-size: .92rem; font-weight: 700; color: #1e293b; line-height: 1.2;
}
.msg-pane-hdr-meta { font-size: .72rem; color: #64748b; margin-top: 2px; }

/* Thread scroll */
.msg-thread {
    flex: 1;
    overflow-y: scroll;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    padding: 20px 26px;
    background: #f0f3f9;
}
.msg-thread::-webkit-scrollbar          { width: 5px; }
.msg-thread::-webkit-scrollbar-track    { background: transparent; }
.msg-thread::-webkit-scrollbar-thumb    { background: #cbd5e1; border-radius: 3px; }

/* Date separator */
.msg-date-sep {
    display: flex; align-items: center; gap: 10px; margin: 14px 0;
}
.msg-date-sep hr { flex: 1; margin: 0; border-color: #cdd5e0; }
.msg-date-sep-label {
    font-size: .64rem; font-weight: 600; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .07em; white-space: nowrap;
}

/* Message rows */
.msg-row {
    display: flex; align-items: flex-end; gap: 9px; margin-bottom: 16px;
}
.msg-row.msg-right { flex-direction: row-reverse; }
.msg-sender-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem; font-weight: 700; color: #fff; flex-shrink: 0;
}
.msg-bubble-wrap { max-width: 70%; }
.msg-meta-row {
    display: flex; align-items: baseline; gap: 5px; margin-bottom: 4px;
    font-size: .69rem;
}
.msg-row.msg-right .msg-meta-row { justify-content: flex-end; }
.msg-sender-name   { font-weight: 600; color: #475569; }
.msg-sent-time     { color: #94a3b8; }
.msg-internal-pill {
    font-size: .6rem; font-weight: 600;
    color: #7c3aed; background: #f3eeff;
    padding: 1px 7px; border-radius: 10px;
}
.msg-bubble {
    padding: 10px 15px;
    font-size: .875rem; line-height: 1.55;
    word-break: break-word;
}
.msg-bubble-admin {
    background: #6f42c1; color: #fff;
    border-radius: 18px 4px 18px 18px;
    box-shadow: 0 2px 8px rgba(111,66,193,.22);
}
.msg-bubble-clinician {
    background: #4361ee; color: #fff;
    border-radius: 4px 18px 18px 18px;
    box-shadow: 0 2px 8px rgba(67,97,238,.18);
}
.msg-bubble-patient {
    background: #fff; color: #212529;
    border-radius: 4px 18px 18px 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.msg-bubble-system {
    background: #f1f4f8; color: #475569;
    border-radius: 12px;
    border: 1px dashed #cbd5e1;
    font-size: .82rem;
}

/* Compose */
.msg-compose {
    border-top: 1px solid #e2e8f0;
    padding: 13px 20px;
    background: #fff;
    flex-shrink: 0;
}
.msg-compose-label {
    font-size: .71rem; color: #64748b; margin-bottom: 8px;
}
.msg-compose-row { display: flex; gap: 10px; align-items: flex-end; }
.msg-compose-row textarea {
    flex: 1;
    border: 1px solid #d1d9e6;
    border-radius: 10px;
    padding: 9px 13px;
    font-size: .875rem;
    font-family: inherit;
    line-height: 1.5;
    resize: none;
    min-height: 44px;
    transition: border-color .15s, box-shadow .15s;
}
.msg-compose-row textarea:focus {
    outline: none;
    border-color: #4361ee;
    box-shadow: 0 0 0 3px rgba(67,97,238,.12);
}
.msg-compose-send {
    height: 44px; padding: 0 18px;
    background: #4361ee; color: #fff;
    border: none; border-radius: 10px;
    font-size: .84rem; font-weight: 600;
    display: flex; align-items: center; gap: 6px;
    cursor: pointer; white-space: nowrap; flex-shrink: 0;
    transition: background .15s;
}
.msg-compose-send:hover { background: #3451d9; }

/* Placeholders */
.msg-no-sel {
    flex: 1; display: flex; align-items: center;
    justify-content: center; color: #94a3b8; background: #f8f9fc;
}
.msg-list-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 48px 20px; color: #94a3b8; text-align: center;
}
.msg-no-clinician {
    font-size: .82rem; color: #64748b;
    padding: 12px 16px; background: #f8f9fc;
    border-radius: 8px; border: 1px solid #e2e8f0;
}

/* Responsive: stack at narrow widths */
@media (max-width: 767.98px) {
    .msg-body { flex-direction: column; }
    .msg-rail { width: 100%; border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 220px; }
}
</style>


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

<div class="msg-card">
    <div class="msg-body" id="msgPaneRow">

        {{-- ── LEFT RAIL ──────────────────────────────────────────── --}}
        <div class="msg-rail">

            <div class="msg-rail-hdr">
                <span class="msg-rail-label">
                    @if($tab === 'patient') Patients @else Providers @endif
                </span>
                @if($cases->total() > 0)
                    <span class="msg-rail-count">{{ $cases->total() }}</span>
                @endif
            </div>

            <div class="msg-list">
                @forelse($cases as $case)
                @php
                    $preview  = $latestByCase->get($case->id);
                    $isActive = $selected && $selected->id === $case->id;
                    $unread   = (int)($case->unread_count ?? 0);
                    $pname    = trim(($case->patient?->first_name ?? '') . ' ' . ($case->patient?->last_name ?? ''));
                    $initials = strtoupper(collect(explode(' ', $pname))->map(fn($p) => mb_substr($p,0,1))->take(2)->implode(''));
                    $avBg     = $tab === 'patient' ? '#4361ee' : '#6f42c1';
                @endphp
                <a href="{{ route('admin.messages.index', ['tab' => $tab, 'case' => $case->uuid]) }}"
                   class="msg-item {{ $isActive ? 'is-active' : '' }}">

                    <div class="msg-avatar-sm" style="background:{{ $avBg }};">
                        {{ $initials ?: '?' }}
                    </div>

                    <div class="msg-item-content">
                        <div class="msg-item-row1">
                            <span class="msg-item-name">{{ $pname ?: 'Unknown Patient' }}</span>
                            <span class="msg-item-time">
                                {{ $case->last_message_at ? \Carbon\Carbon::parse($case->last_message_at)->diffForHumans(null, true) : '' }}
                            </span>
                        </div>
                        <div class="msg-item-row2">
                            <span class="msg-item-preview">
                                @if($preview)
                                    {{ \Illuminate\Support\Str::limit($preview->body, 52) }}
                                @else
                                    {{ $case->partner?->name ?? '' }}
                                @endif
                            </span>
                            @if($unread > 0)
                                <span class="msg-item-badge">{{ $unread }}</span>
                            @endif
                        </div>
                        @if($tab === 'provider')
                            <div class="msg-item-sub">
                                <i class="bi bi-person-badge me-1"></i>{{ $case->clinician?->full_name ?? 'Unassigned' }}
                            </div>
                        @endif
                    </div>
                </a>

                @empty
                <div class="msg-list-empty">
                    <i class="bi bi-chat-square-text" style="font-size:2rem;opacity:.2;margin-bottom:8px;"></i>
                    <span style="font-size:.8rem;">
                        @if($tab === 'patient') No patient conversations yet. @else No provider threads yet. @endif
                    </span>
                </div>
                @endforelse
            </div>

            @if($cases->hasPages())
            <div class="msg-rail-footer">
                {{ $cases->links('pagination::simple-bootstrap-5') }}
            </div>
            @endif

        </div>{{-- /msg-rail --}}

        {{-- ── RIGHT PANE ─────────────────────────────────────────── --}}
        <div class="msg-pane">

            @if($selected)
            @php
                $pname = trim(($selected->patient?->first_name ?? '') . ' ' . ($selected->patient?->last_name ?? ''));
            @endphp

            {{-- Header --}}
            <div class="msg-pane-hdr">
                <div style="flex:1;min-width:0;">
                    <div class="msg-pane-hdr-name">{{ $pname ?: 'Unknown Patient' }}</div>
                    <div class="msg-pane-hdr-meta">
                        {{ $selected->partner?->name ?? '' }}
                        @if($tab === 'provider' && $selected->clinician)
                            &middot; {{ $selected->clinician->full_name }}
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.cases.show', $selected->uuid) }}"
                   class="btn btn-sm btn-outline-secondary px-3 py-1 flex-shrink-0"
                   style="font-size:.78rem;">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Open case
                </a>
            </div>

            {{-- Thread --}}
            <div class="msg-thread" id="msgThread">
                @php $prevDate = null; @endphp

                @forelse($thread as $msg)
                @php
                    $msgDate = $msg->created_at->format('Y-m-d');
                    $isInternal = $msg->channel === 'internal';

                    if ($msg->sender_type === 'admin') {
                        $avBg       = '#6f42c1';
                        $senderName = $msg->user?->name ?? 'Admin';
                        $right      = true;
                        $bClass     = 'msg-bubble-admin';
                    } elseif ($msg->sender_type === 'clinician') {
                        $avBg       = '#4361ee';
                        $senderName = $msg->clinician?->user?->name ?? 'Clinician';
                        $right      = false;
                        $bClass     = 'msg-bubble-clinician';
                    } elseif ($msg->sender_type === 'patient') {
                        $avBg       = '#16a34a';
                        $senderName = $pname ?: 'Patient';
                        $right      = false;
                        $bClass     = 'msg-bubble-patient';
                    } else {
                        $avBg       = '#6c757d';
                        $senderName = ucfirst($msg->sender_type ?? 'System');
                        $right      = false;
                        $bClass     = 'msg-bubble-system';
                    }

                    $initials = strtoupper(
                        collect(explode(' ', trim($senderName)))
                            ->map(fn($p) => mb_substr($p, 0, 1))
                            ->take(2)
                            ->implode('')
                    );
                @endphp

                @if($msgDate !== $prevDate)
                    @php $prevDate = $msgDate; @endphp
                    <div class="msg-date-sep">
                        <hr>
                        <span class="msg-date-sep-label">
                            {{ $msg->created_at->isToday() ? 'Today'
                               : ($msg->created_at->isYesterday() ? 'Yesterday'
                                  : $msg->created_at->format('M j, Y')) }}
                        </span>
                        <hr>
                    </div>
                @endif

                <div class="msg-row {{ $right ? 'msg-right' : '' }}">
                    <div class="msg-sender-avatar" style="background:{{ $avBg }};">
                        {{ $initials ?: '?' }}
                    </div>
                    <div class="msg-bubble-wrap">
                        <div class="msg-meta-row">
                            <span class="msg-sender-name">{{ $senderName }}</span>
                            <span class="msg-sent-time">{{ $msg->created_at->format('H:i') }}</span>
                            @if($isInternal)
                                <span class="msg-internal-pill">internal</span>
                            @endif
                        </div>
                        <div class="msg-bubble {{ $bClass }}">{{ $msg->body }}</div>
                    </div>
                </div>

                @empty
                <div style="text-align:center;padding:48px 0;color:#94a3b8;font-size:.84rem;">
                    @if($tab === 'patient')
                        No messages on this case yet.
                    @else
                        No internal messages yet. Use the compose area below to write to the clinician.
                    @endif
                </div>
                @endforelse
            </div>

            {{-- Compose (provider tab only) --}}
            @if($tab === 'provider')
            <div class="msg-compose">
                @if($selected->clinician_id)
                <form id="msgComposeForm" method="POST" action="{{ route('admin.messages.send') }}">
                    @csrf
                    <input type="hidden" name="case_uuid" value="{{ $selected->uuid }}">
                    <div class="msg-compose-label">
                        Message <strong>{{ $selected->clinician->full_name }}</strong>
                        <span style="color:#94a3b8;">&mdash; internal note, not visible to patient</span>
                    </div>
                    <div class="msg-compose-row">
                        <textarea name="body" rows="2" required maxlength="5000"
                                  placeholder="Write an internal note to the clinician…"></textarea>
                        <button id="msgSendBtn" type="submit" class="msg-compose-send">
                            <i class="bi bi-send-fill"></i>Send
                        </button>
                    </div>
                </form>
                @else
                <div class="msg-no-clinician">
                    <i class="bi bi-info-circle me-1"></i>
                    No clinician assigned to this case.
                    <a href="{{ route('admin.cases.show', $selected->uuid) }}">Assign one</a>
                    before sending an internal message.
                </div>
                @endif
            </div>
            @endif

            @else
            {{-- Nothing selected --}}
            <div class="msg-no-sel">
                <div class="text-center">
                    <i class="bi bi-chat-square-text" style="font-size:2.5rem;opacity:.18;display:block;margin-bottom:10px;"></i>
                    <div style="font-size:.84rem;">Select a conversation from the list</div>
                </div>
            </div>
            @endif

        </div>{{-- /msg-pane --}}

    </div>{{-- /msg-body --}}
</div>{{-- /msg-card --}}

@endsection

@section('scripts')
<script>
(function () {
    // Fit the two-pane container to exactly the remaining viewport height
    // so neither the page body nor the container overflows. Using
    // getBoundingClientRect().top avoids hardcoding the offset above the row
    // (tabs, alerts, page padding all vary per user role).
    var row = document.getElementById('msgPaneRow');
    if (row) {
        function fitRow() {
            var top = row.getBoundingClientRect().top;
            // 26 = page-content bottom-padding (24px) + card border-bottom (1px) + 1px buffer
            var h = Math.max(window.innerHeight - top - 26, 520);
            row.style.height = h + 'px';
        }
        // Defer one rAF so the sticky topbar layout has settled.
        requestAnimationFrame(fitRow);
        window.addEventListener('resize', fitRow);
    }

    // Auto-scroll thread to latest message on load.
    var thread = document.getElementById('msgThread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    // Prevent double-submission on the compose form.
    // Disabling the button on submit stops a second click from firing a
    // duplicate POST before the redirect completes, which was the source
    // of two identical success alerts appearing on the messages page.
    // bfcache restore re-enables the button so the form stays usable if
    // the user navigates back without a full reload.
    var composeForm = document.getElementById('msgComposeForm');
    var sendBtn     = document.getElementById('msgSendBtn');
    if (composeForm && sendBtn) {
        composeForm.addEventListener('submit', function () {
            sendBtn.disabled = true;
            sendBtn.style.opacity = '0.55';
        });
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                sendBtn.disabled = false;
                sendBtn.style.opacity = '';
            }
        });
    }
})();
</script>
@endsection
