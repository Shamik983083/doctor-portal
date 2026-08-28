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
        @php $fwdMsgs = $thread->where('direction','inbound')->values(); @endphp
        <div id="fwdModal" class="fwd-modal-overlay" style="display:none;">
            <div class="fwd-modal-box">
                <div class="fwd-modal-head">
                    <h3>Forward to Support</h3>
                    <p>Select one or more patient messages to share with the support team, then add an optional note.</p>
                    <button type="button" class="fwd-close-btn" onclick="closeFwdModal()" aria-label="Close">&times;</button>
                </div>
                <form id="fwdForm" method="POST" action="{{ route('clinician.cases.forward-to-support', $selected->uuid) }}"
                      onsubmit="return compileFwdMsg(this)"
                      style="display:flex;flex-direction:column;overflow:hidden;flex:1;min-height:0;">
                    @csrf
                    <input type="hidden" name="quoted_message" id="fwdQuotedInput">
                    <div class="fwd-msg-section">
                        <div class="fwd-msg-section-head">
                            <span class="fwd-msg-section-label">
                                Patient Messages
                                <span class="fwd-count-badge" id="fwdCount" style="display:none;"></span>
                            </span>
                            @if($fwdMsgs->count() > 1)
                            <button type="button" class="fwd-select-all-btn" id="fwdSelectAll"
                                    onclick="fwdToggleAll('fwdMsgList','fwdSelectAll','fwdCount')">Select all</button>
                            @endif
                        </div>
                        <div class="fwd-msg-list" id="fwdMsgList">
                            @forelse($fwdMsgs as $msg)
                            <label class="fwd-msg-item"
                                   data-body="{{ e($msg->body) }}"
                                   data-time="{{ $msg->created_at->format('M j, g:i A') }}">
                                <input type="checkbox" class="fwd-check"
                                       onchange="fwdCheckChange('fwdMsgList','fwdCount','fwdSelectAll')">
                                <div class="fwd-msg-content">
                                    <span class="fwd-msg-time">{{ $msg->created_at->format('M j, g:i A') }}</span>
                                    <span class="fwd-msg-body">{{ $msg->body }}</span>
                                </div>
                            </label>
                            @empty
                            <div class="fwd-empty-state">
                                <i class="bi bi-chat-text" style="font-size:1.4rem;display:block;margin-bottom:6px;opacity:.35;"></i>
                                No patient messages yet. Add a note below to open the support thread.
                            </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="fwd-note-section">
                        <label>Your note to support <span style="color:var(--muted,#64748b);font-weight:400;">(optional)</span></label>
                        <textarea name="note" class="fwd-note-textarea" rows="2"
                                  placeholder="Add context or a specific question for the support team…"></textarea>
                    </div>
                    <div class="fwd-error-msg" id="fwdError">Please select at least one message, or add a note.</div>
                    <div class="fwd-footer">
                        <button type="button" class="fwd-cancel-btn" onclick="closeFwdModal()">Cancel</button>
                        <button type="submit" class="fwd-submit-btn">
                            <i class="bi bi-send-fill"></i> Open Support Thread
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
<style>
.fwd-modal-overlay { position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:16px; }
.fwd-modal-box { background:var(--surface,#fff);border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.18);max-width:520px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden; }
.fwd-modal-head { padding:20px 22px 14px;border-bottom:1px solid var(--line,#e2e8f0);position:relative;flex-shrink:0; }
.fwd-modal-head h3 { font-size:1rem;font-weight:700;margin:0 0 3px;color:var(--ink,#1e293b); }
.fwd-modal-head p { font-size:.78rem;color:var(--muted,#64748b);margin:0;line-height:1.45; }
.fwd-close-btn { position:absolute;top:14px;right:14px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted,#64748b);line-height:1;padding:3px 7px;border-radius:6px; }
.fwd-close-btn:hover { background:var(--line,#f1f5f9);color:var(--ink,#1e293b); }
.fwd-msg-section { padding:14px 22px 0;flex-shrink:0; }
.fwd-msg-section-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:8px; }
.fwd-msg-section-label { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#64748b); }
.fwd-count-badge { display:inline-block;margin-left:6px;font-size:.68rem;background:#e0e7ff;color:#3730a3;border-radius:20px;padding:1px 8px;font-weight:700; }
.fwd-select-all-btn { font-size:.73rem;color:#4361ee;background:none;border:none;cursor:pointer;padding:0;font-weight:500; }
.fwd-select-all-btn:hover { text-decoration:underline; }
.fwd-msg-list { max-height:210px;overflow-y:auto;border:1px solid var(--line,#e2e8f0);border-radius:10px; }
.fwd-msg-item { display:flex;align-items:flex-start;gap:10px;padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .12s;user-select:none; }
.fwd-msg-item:last-child { border-bottom:none; }
.fwd-msg-item:hover { background:#f8fafc; }
.fwd-msg-item.fwd-selected { background:#eff6ff;border-left:3px solid #4361ee; }
.fwd-check { width:16px;height:16px;margin-top:2px;flex-shrink:0;accent-color:#4361ee;cursor:pointer; }
.fwd-msg-content { flex:1;min-width:0; }
.fwd-msg-time { display:block;font-size:.68rem;color:var(--muted,#64748b);margin-bottom:2px; }
.fwd-msg-body { display:block;font-size:.82rem;color:var(--ink,#1e293b);line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical; }
.fwd-empty-state { padding:20px;text-align:center;color:var(--muted,#64748b);font-size:.82rem; }
.fwd-note-section { padding:12px 22px 0;flex-shrink:0; }
.fwd-note-section label { font-size:.78rem;font-weight:600;display:block;margin-bottom:5px;color:var(--ink,#1e293b); }
.fwd-note-textarea { width:100%;border:1px solid var(--line,#e2e8f0);border-radius:8px;padding:8px 10px;font-size:.82rem;resize:none;background:var(--surface,#fff);color:var(--ink,#1e293b);box-sizing:border-box; }
.fwd-note-textarea:focus { outline:none;border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,.12); }
.fwd-error-msg { margin:8px 22px 0;padding:8px 12px;background:#fef2f2;border-radius:8px;border:1px solid #fecaca;font-size:.78rem;color:#dc2626;display:none; }
.fwd-footer { padding:14px 22px 18px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid var(--line,#e2e8f0);flex-shrink:0;margin-top:12px; }
.fwd-cancel-btn { padding:8px 18px;border:1px solid var(--line,#e2e8f0);border-radius:8px;background:none;cursor:pointer;font-size:.83rem;color:var(--ink,#1e293b); }
.fwd-cancel-btn:hover { background:#f8fafc; }
.fwd-submit-btn { padding:8px 18px;border:none;border-radius:8px;background:#4361ee;color:#fff;font-weight:600;cursor:pointer;font-size:.83rem;display:flex;align-items:center;gap:6px; }
.fwd-submit-btn:hover { background:#3451d1; }
</style>
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

    // fwdCheckChange and fwdToggleAll are defined on cases/show too — safe to
    // re-declare here since these are separate pages.
    window.fwdCheckChange = function (listId, countId, selectAllId) {
        var list = document.getElementById(listId);
        if (!list) return;
        var all     = list.querySelectorAll('.fwd-check');
        var checked = list.querySelectorAll('.fwd-check:checked');
        all.forEach(function (cb) {
            cb.closest('.fwd-msg-item').classList.toggle('fwd-selected', cb.checked);
        });
        var countEl = document.getElementById(countId);
        if (countEl) {
            countEl.style.display = checked.length > 0 ? '' : 'none';
            countEl.textContent   = checked.length + ' selected';
        }
        var saBtn = document.getElementById(selectAllId);
        if (saBtn && all.length > 0) {
            saBtn.textContent = checked.length === all.length ? 'Deselect all' : 'Select all';
        }
    };

    window.fwdToggleAll = function (listId, selectAllId, countId) {
        var list = document.getElementById(listId);
        if (!list) return;
        var all        = list.querySelectorAll('.fwd-check');
        var allChecked = list.querySelectorAll('.fwd-check:checked').length === all.length;
        all.forEach(function (cb) { cb.checked = !allChecked; });
        window.fwdCheckChange(listId, countId, selectAllId);
    };

    window.compileFwdMsg = function (form) {
        var list      = document.getElementById('fwdMsgList');
        var hiddenInp = document.getElementById('fwdQuotedInput');
        var errorEl   = document.getElementById('fwdError');
        var noteField = form.querySelector('[name="note"]');
        var note      = noteField ? noteField.value.trim() : '';
        var selected  = list ? list.querySelectorAll('.fwd-check:checked') : [];

        if (selected.length === 0 && !note) {
            if (errorEl) errorEl.style.display = '';
            return false;
        }
        if (errorEl) errorEl.style.display = 'none';

        var quoted = '';
        selected.forEach(function (cb) {
            var item = cb.closest('.fwd-msg-item');
            quoted += '[' + (item.dataset.time || '') + ']\n"' + (item.dataset.body || '') + '"\n\n';
        });
        if (hiddenInp) hiddenInp.value = quoted.trim();
        return true;
    };

    window.openFwdModal = function () {
        if (fwdModal) { fwdModal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    };

    window.closeFwdModal = function () {
        if (!fwdModal) return;
        fwdModal.style.display = 'none';
        document.body.style.overflow = '';
        var list = document.getElementById('fwdMsgList');
        if (list) {
            list.querySelectorAll('.fwd-check:checked').forEach(function (cb) {
                cb.checked = false;
                cb.closest('.fwd-msg-item').classList.remove('fwd-selected');
            });
            window.fwdCheckChange('fwdMsgList', 'fwdCount', 'fwdSelectAll');
        }
        var err = document.getElementById('fwdError');
        if (err) err.style.display = 'none';
    };

    if (fwdModal) {
        fwdModal.addEventListener('click', function (e) {
            if (e.target === fwdModal) window.closeFwdModal();
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
