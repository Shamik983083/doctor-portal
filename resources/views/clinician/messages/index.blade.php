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
        @foreach($cases as $c)
            @php($msg = $latest->get($c->id))
            <a class="msg-row {{ $selected && $selected->id === $c->id ? 'active' : '' }}"
               href="{{ route('clinician.messages.index', array_merge(request()->except('page'), ['case' => $c->uuid])) }}">
                <span class="msg-avatar">{{ $initials($c->patient?->full_name) }}</span>
                <span class="msg-row-body">
                    <strong>{{ $c->patient?->full_name ?? 'Unknown patient' }}</strong>
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
    <div class="panel chat">
        <div class="chat-head">
            <span class="msg-avatar big">{{ $initials($selected->patient?->full_name) }}</span>
            <div>
                <strong>{{ $selected->patient?->full_name ?? 'Unknown patient' }}</strong>
                <span>{{ $selected->partner?->name ?? '' }} · case {{ $selected->external_id ?? \Illuminate\Support\Str::limit($selected->uuid, 8, '') }}</span>
            </div>
            <a class="button-secondary" style="margin-left:auto" href="{{ route('clinician.cases.show', $selected->uuid) }}">Open case</a>
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
    // Keep the thread scrolled to the newest message.
    (function () {
        var s = document.getElementById('chatScroll');
        if (s) s.scrollTop = s.scrollHeight;
    })();
</script>
@endsection
