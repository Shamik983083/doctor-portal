@extends('layouts.clinician')

@section('title', 'Messages For Provider')
@section('page-title', 'Messages For Provider')

@section('content')
<div class="ma-surface">

    <div class="card" id="messagesInbox">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="ma-eyebrow">Tasks</div>
                    <div class="ma-title">Messages For Provider</div>
                    <div class="ma-sub">Conversations on your cases. Open one to read and reply.</div>
                </div>
                <div class="align-self-center btn-group btn-group-sm" role="group">
                    <a href="{{ route('clinician.messages.index') }}"
                       class="btn btn-outline-secondary {{ !request('unread') ? 'active' : '' }}">All</a>
                    <a href="{{ route('clinician.messages.index') }}?unread=1"
                       class="btn btn-outline-secondary {{ request('unread') ? 'active' : '' }}">Unread</a>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Company</th>
                            <th>Last message</th>
                            <th>When</th>
                            <th>Unread</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                            @php($msg = $latest->get($case->id))
                            <tr>
                                <td class="fw-semibold">{{ $case->patient?->full_name ?? 'Unknown patient' }}</td>
                                <td>{{ $case->partner?->name ?? '—' }}</td>
                                <td class="text-muted" style="max-width:360px;">
                                    @if($msg)
                                        <span class="badge bg-light text-secondary border me-1">{{ ucfirst($msg->direction) }}</span>
                                        {{ \Illuminate\Support\Str::limit($msg->body, 80) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><small>{{ $case->last_message_at ? \Illuminate\Support\Carbon::parse($case->last_message_at)->diffForHumans(null, true) : '—' }}</small></td>
                                <td>
                                    @if($case->unread_messages_count > 0)
                                        <span class="badge bg-danger">{{ $case->unread_messages_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('clinician.cases.show', $case->uuid) }}#messages" class="btn btn-sm btn-primary">Open thread &rarr;</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-chat-dots fs-2 d-block mb-2 opacity-25"></i>
                                No messages yet. Conversations on your cases will appear here.
                            </td></tr>
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
