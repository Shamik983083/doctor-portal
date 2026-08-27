@extends('layouts.clinician-exact')

@section('title', 'Messages For Provider')
@section('page-title', 'Messages For Provider')

{{--
    Messages For Provider, rebuilt to the preview's two-pane chat on the exact
    shell (Devin msg 2294: match the UI/UX to what we have). Left: the
    conversation list. Right: the selected thread as iMessage-style bubbles with
    a compose box that posts to the real send endpoint. Real data throughout.
--}}

@php
    $initials = function ($name) {
        return strtoupper(collect(explode(' ', trim($name ?: '')))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    };
@endphp

@section('view')
<div class="page-head">
    <div class="eyebrow">Tasks</div>
    <h1>Messages For Provider</h1>
    <p>Conversations on your cases. Pick one to read and reply.</p>
</div>

@if($cases->isEmpty())
    <section class="panel"><div class="stub"><strong>No messages yet</strong>Conversations on your cases will appear here.</div></section>
@else
<div class="msg-layout">

    {{-- Left: conversation list --}}
    <div class="panel msg-list">
        <div class="panel-heading"><div><h2>Conversations</h2><p>{{ $cases->total() }} with messages.</p></div></div>

        {{-- A8: Search + filter bar --}}
        <form method="GET" action="{{ route('clinician.messages.index') }}" style="padding:10px 14px 0;display:flex;flex-direction:column;gap:8px">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Search by patient name or email…"
                   style="width:100%;padding:7px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;background:var(--surface)">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                @foreach(['all' => 'All', 'unread' => 'Unread', 'waiting' => 'Waiting reply', 'read' => 'Read'] as $fv => $fl)
                    @php $fActive = ($fv === 'all' ? !request('filter') : request('filter') === $fv); @endphp
                    <a href="{{ route('clinician.messages.index', array_merge(request()->except(['filter','page']), $fv !== 'all' ? ['filter' => $fv] : [])) }}"
                       class="pill {{ $fActive ? 'green' : 'neutral' }}" style="text-decoration:none;padding:3px 10px;font-size:12px">{{ $fl }}</a>
                @endforeach
            </div>
        </form>

        <div style="height:8px"></div>

        @foreach($cases as $c)
            @php($msg = $latest->get($c->id))
            <a class="msg-row {{ $selected && $selected->id === $c->id ? 'active' : '' }}"
               data-uuid="{{ $c->uuid }}"
               href="{{ route('clinician.messages.index', array_merge(request()->except('page'), ['case' => $c->uuid])) }}">
                <span class="msg-avatar">{{ $initials($c->patient?->full_name) }}</span>
                <span class="msg-row-body">
                    <span class="d-flex align-items-center gap-1 flex-wrap">
                        <strong>{{ $c->patient?->full_name ?? 'Unknown patient' }}</strong>
                        @if($c->escalation_target === 'support' && $c->support_at)
                        <span style="font-size:.6rem;font-weight:700;background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 5px;letter-spacing:.04em;line-height:1.4;">SUPPORT</span>
                        @endif
                    </span>
                    <span>{{ $msg ? \Illuminate\Support\Str::limit($msg->body, 54) : ($c->partner?->name ?? '') }}</span>
                </span>
                <span class="msg-row-meta">
                    {{ $c->last_message_at ? \Illuminate\Support\Carbon::parse($c->last_message_at)->diffForHumans(null, true) : '' }}
                    @if($c->unread_messages_count > 0)<span class="msg-unread">{{ $c->unread_messages_count }}</span>@endif
                </span>
            </a>
        @endforeach
        @if($cases->hasPages())
            <div style="padding:12px 16px">{{ $cases->links() }}</div>
        @endif
    </div>

    {{-- Right: the thread --}}
    <div class="panel chat" data-selected-uuid="{{ $selected?->uuid }}" id="chatPanel">
        <div class="chat-head">
            <span class="msg-avatar big">{{ $initials($selected->patient?->full_name) }}</span>
            <div>
                <strong>{{ $selected->patient?->full_name ?? 'Unknown patient' }}</strong>
                <span>{{ $selected->partner?->name ?? '' }} · case {{ $selected->external_id ?? \Illuminate\Support\Str::limit($selected->uuid, 8, '') }}</span>
            </div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                @if($selected->escalation_target === 'support' && $selected->support_at)
                <span style="font-size:.75rem;background:#fef3c7;color:#92400e;border-radius:6px;padding:4px 10px;font-weight:600;white-space:nowrap;">
                    <i class="bi bi-headset"></i> Support thread open
                </span>
                @else
                <button type="button" class="button-secondary" id="fwdSupportBtn"
                        onclick="openFwdModal()"
                        style="font-size:.8rem;padding:5px 12px;white-space:nowrap;">
                    <i class="bi bi-send"></i> Forward to Support
                </button>
                @endif
                <a class="button-secondary" style="font-size:.8rem;padding:5px 12px;" href="{{ route('clinician.cases.show', $selected->uuid) }}">Open case</a>
            </div>
        </div>

        {{-- Forward to Support modal --}}
        <div id="fwdModal" style="display:none;position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
            <div style="background:var(--surface);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);padding:24px 28px;max-width:480px;width:92%;position:relative;">
                <button type="button" onclick="closeFwdModal()" aria-label="Close"
                        style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:1.2rem;color:var(--muted);cursor:pointer;">&times;</button>
                <h3 style="font-size:1rem;font-weight:700;margin:0 0 4px;">Forward to Support</h3>
                <p style="font-size:.8rem;color:var(--muted);margin:0 0 16px;">This opens a private thread with the partner's support team. The patient conversation is unaffected.</p>
                <form id="fwdForm" method="POST" action="{{ route('clinician.cases.forward-to-support', $selected->uuid) }}">
                    @csrf
                    <input type="hidden" name="quoted_message" id="fwdQuotedInput">
                    <div style="margin-bottom:14px;">
                        <label style="font-size:.78rem;font-weight:600;display:block;margin-bottom:4px;">Message to forward <span style="color:var(--muted);font-weight:400;">(pre-filled from last patient message)</span></label>
                        <textarea id="fwdQuotedDisplay" rows="3"
                                  style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:.82rem;background:var(--surface);resize:vertical;"
                                  placeholder="Paste or type the patient message to forward…"
                                  oninput="document.getElementById('fwdQuotedInput').value=this.value"></textarea>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label style="font-size:.78rem;font-weight:600;display:block;margin-bottom:4px;">Your note to support <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                        <textarea name="note" rows="2"
                                  style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:.82rem;background:var(--surface);resize:none;"
                                  placeholder="Add context, specific question, or any details for the support team…"></textarea>
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeFwdModal()"
                                style="padding:7px 18px;border:1px solid var(--line);border-radius:8px;background:none;cursor:pointer;font-size:.83rem;">Cancel</button>
                        <button type="submit"
                                style="padding:7px 18px;border:none;border-radius:8px;background:#4361ee;color:#fff;font-weight:600;cursor:pointer;font-size:.83rem;">
                            <i class="bi bi-send me-1"></i> Open Support Thread
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="chat-scroll" id="chatScroll">
            @php($lastSide = null)
            @forelse($thread as $m)
                @php($side = $m->direction === 'outbound' ? 'me' : 'them')
                @if($side !== $lastSide)
                    <div class="chat-time">{{ $m->created_at->format('M j, g:i A') }}</div>
                    @php($lastSide = $side)
                @endif
                <div class="bubble-row {{ $side }}"><div class="bubble {{ $side }}">{{ $m->body }}</div></div>
            @empty
                <div class="chat-time">No messages in this conversation yet. Send the first one below.</div>
            @endforelse
            @if($thread->isNotEmpty() && $thread->last()->direction === 'outbound')
                <div class="chat-read">Delivered</div>
            @endif
        </div>

        <form method="POST" action="{{ route('clinician.cases.messages.store', $selected->uuid) }}" class="chat-compose">
            @csrf
            <input type="text" name="body" id="composeBox" placeholder="Message" aria-label="Message" autocomplete="off" required>
            <button type="submit" class="chat-send" aria-label="Send">&uarr;</button>
        </form>
    </div>

</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    // Scroll thread to bottom on load
    var scroll = document.getElementById('chatScroll');
    if (scroll) scroll.scrollTop = scroll.scrollHeight;

    var msgList    = document.querySelector('.msg-list');
    var chatPanel  = document.getElementById('chatPanel');
    var selectedUuid = chatPanel ? chatPanel.dataset.selectedUuid : null;
    var csrf       = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }

    // ── Toast for new messages in other conversations ────────────────
    function showToast(patientName, snippet, caseUuid) {
        var existing = document.getElementById('msgToast');
        if (existing) existing.remove();
        var url = '{{ route("clinician.messages.index") }}?case=' + caseUuid;
        var t = document.createElement('div');
        t.id = 'msgToast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#172033;color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.25);max-width:320px;display:flex;flex-direction:column;gap:6px;animation:slideUp .25s ease';
        t.innerHTML = '<strong style="font-size:.85rem;">New message from ' + esc(patientName) + '</strong>'
            + '<span style="font-size:.78rem;color:#94a3b8;">' + esc(snippet) + '</span>'
            + '<a href="' + url + '" style="font-size:.78rem;color:#60a5fa;margin-top:2px;">Open conversation &rarr;</a>';
        document.body.appendChild(t);
        setTimeout(function () { if (t.parentNode) t.remove(); }, 6000);
    }

    // ── Update or prepend a conversation row in the left panel ───────
    function upsertConversationRow(data) {
        if (!msgList) return;
        var url    = '{{ route("clinician.messages.index") }}?case=' + data.caseUuid;
        var existing = msgList.querySelector('.msg-row[data-uuid="' + data.caseUuid + '"]');

        if (existing) {
            // Update snippet and time
            var snippetEl = existing.querySelector('.msg-row-body span');
            var timeEl    = existing.querySelector('.msg-row-meta');
            var badgeEl   = existing.querySelector('.msg-unread');
            if (snippetEl) snippetEl.textContent = data.snippet;
            if (timeEl) {
                // Update time text (keep badge if present)
                var badge = badgeEl ? badgeEl.outerHTML : '';
                timeEl.innerHTML = 'just now ' + (existing.classList.contains('active') ? '' : badge || '<span class="msg-unread">1</span>');
            }
            if (!existing.classList.contains('active')) {
                var initials = data.patientName.split(' ').map(function(p){return p[0]||'';}).slice(0,2).join('').toUpperCase();
                // Move to top of list (after the heading)
                var heading = msgList.querySelector('.panel-heading');
                msgList.insertBefore(existing, heading ? heading.nextSibling : msgList.firstChild);
                existing.style.background = '#eff6ff';
                setTimeout(function(){ existing.style.background = ''; }, 3000);
            }
        } else {
            // New conversation not in list — prepend a row
            var initials = data.patientName.split(' ').map(function(p){return p[0]||'';}).slice(0,2).join('').toUpperCase();
            var row = document.createElement('a');
            row.className = 'msg-row';
            row.setAttribute('data-uuid', data.caseUuid);
            row.href = url;
            row.style.background = '#eff6ff';
            row.innerHTML = '<span class="msg-avatar">' + esc(initials) + '</span>'
                + '<span class="msg-row-body"><strong>' + esc(data.patientName) + '</strong><span>' + esc(data.snippet) + '</span></span>'
                + '<span class="msg-row-meta">just now <span class="msg-unread">1</span></span>';
            var heading = msgList.querySelector('.panel-heading');
            msgList.insertBefore(row, heading ? heading.nextSibling : msgList.firstChild);
            setTimeout(function(){ row.style.background = ''; }, 3000);
        }
    }

    // ── Append an inbound bubble to the open thread ──────────────────
    function appendInboundBubble(snippet) {
        if (!scroll) return;
        var bubble = document.createElement('div');
        bubble.className = 'bubble-row them';
        bubble.innerHTML = '<div class="bubble them">' + esc(snippet) + '</div>';
        scroll.appendChild(bubble);
        scroll.scrollTop = scroll.scrollHeight;
    }

    // ── Handle an incoming NewPatientMessage event ────────────────────
    function handleNewMessage(data) {
        upsertConversationRow(data);
        if (selectedUuid && selectedUuid === data.caseUuid) {
            appendInboundBubble(data.snippet);
        } else {
            showToast(data.patientName, data.snippet, data.caseUuid);
        }
    }

    // ── Mark conversation as read via AJAX ──────────────────────────
    // Called when a conversation becomes visually focused (on load for the
    // initially selected thread, and on row click before navigating). This
    // keeps the sidebar badge accurate at page render time — the server no
    // longer marks messages read during the page request itself.
    function markRead(caseUuid, rowEl) {
        fetch('{{ url("clinician/messages") }}/' + caseUuid + '/read', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data || !data.marked_read) return;
            // Remove the unread badge from the row in the left panel
            var row = rowEl || (msgList ? msgList.querySelector('.msg-row[data-uuid="' + caseUuid + '"]') : null);
            if (row) {
                var badge = row.querySelector('.msg-unread');
                if (badge) badge.remove();
            }
            // Decrement the global sidebar badge
            var globalBadge = document.getElementById('msgBadge');
            if (globalBadge) {
                var current = parseInt(globalBadge.textContent, 10) || 0;
                var next = Math.max(0, current - data.marked_read);
                globalBadge.textContent = next;
                globalBadge.classList.toggle('zero', next === 0);
            }
        })
        .catch(function () {});
    }

    // Mark the initially selected conversation as read on page load
    if (selectedUuid) {
        markRead(selectedUuid, null);
    }

    // Intercept conversation row clicks: mark the target read before navigating
    if (msgList) {
        msgList.addEventListener('click', function (e) {
            var row = e.target.closest('.msg-row');
            if (!row || !row.dataset.uuid || row.dataset.uuid === selectedUuid) return;
            // Fire and forget — navigation proceeds immediately after
            markRead(row.dataset.uuid, row);
        });
    }

    // ── Forward-to-support modal ─────────────────────────────────────
    var fwdModal = document.getElementById('fwdModal');
    var fwdQuotedDisplay = document.getElementById('fwdQuotedDisplay');
    var fwdQuotedInput   = document.getElementById('fwdQuotedInput');

    window.openFwdModal = function () {
        // Pre-fill with the last inbound (patient) bubble text
        var bubbles = document.querySelectorAll('#chatScroll .bubble.them');
        var lastPatientMsg = bubbles.length ? bubbles[bubbles.length - 1].textContent.trim() : '';
        if (fwdQuotedDisplay) {
            fwdQuotedDisplay.value = lastPatientMsg;
            if (fwdQuotedInput) fwdQuotedInput.value = lastPatientMsg;
        }
        if (fwdModal) { fwdModal.style.display = 'flex'; }
        if (fwdQuotedDisplay) fwdQuotedDisplay.focus();
    };

    window.closeFwdModal = function () {
        if (fwdModal) fwdModal.style.display = 'none';
    };

    // Close on backdrop click
    if (fwdModal) {
        fwdModal.addEventListener('click', function (e) {
            if (e.target === fwdModal) closeFwdModal();
        });
    }

    // Echo is initialised globally by the layout; subscribe to page-specific events here.
    if (window.Echo) {
        window.Echo.private('provider-inbox').listen('.NewPatientMessage', function (e) {
            handleNewMessage({
                caseUuid:    e.caseUuid,
                patientName: e.patientName,
                snippet:     e.snippet,
            });
        });
    } else {
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') window.location.reload();
        });
    }
})();
</script>
@endsection
