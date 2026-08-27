@extends('layouts.clinician-exact')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <span class="text-muted fs-6">
                    @if($unread > 0)
                        <span class="badge bg-danger me-1">{{ $unread }}</span> Unread
                    @else
                        All caught up
                    @endif
                </span>
            </div>
            @if($unread > 0)
            <form method="POST" action="{{ route('clinician.notifications.read-all') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">Mark all as read</button>
            </form>
            @endif
        </div>

        <div class="card shadow-sm border-0">
            @forelse($notifications as $n)
                @php
                    $data    = $n->data;
                    $isRead  = !is_null($n->read_at);
                    $type    = $data['type'] ?? 'info';
                    $iconMap = [
                        'case_assigned' => ['icon' => 'bi-person-check', 'color' => '#f59e0b'],
                        'new_message'   => ['icon' => 'bi-chat-dots',    'color' => '#ec4899'],
                        'info'          => ['icon' => 'bi-info-circle',  'color' => '#6c757d'],
                    ];
                    $icon  = $iconMap[$type]['icon']  ?? 'bi-bell';
                    $color = $iconMap[$type]['color'] ?? '#6c757d';
                    $url   = $data['url'] ?? '#';
                @endphp
                <div class="d-flex align-items-start px-4 py-3 border-bottom {{ $isRead ? '' : 'unread-row' }}"
                     style="{{ $isRead ? '' : 'background:#f0f6ff;' }}">

                    <div class="flex-shrink-0 me-3 mt-1">
                        <span class="d-flex align-items-center justify-content-center rounded-circle"
                              style="width:38px;height:38px;background:{{ $color }}1a;">
                            <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1rem;"></i>
                        </span>
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-{{ $isRead ? 'normal' : 'semibold' }} text-dark" style="font-size:.9rem;">
                                    {{ $data['title'] ?? '' }}
                                </div>
                                <div class="text-muted" style="font-size:.82rem;margin-top:2px;">
                                    {{ $data['body'] ?? '' }}
                                </div>
                                <div class="text-muted mt-1" style="font-size:.75rem;">
                                    {{ $n->created_at->diffForHumans() }}
                                    &middot;
                                    {{ $n->created_at->format('M j, Y H:i') }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                @if(!$isRead)
                                    <span class="badge rounded-pill" style="background:#0d6efd;font-size:.65rem;">New</span>
                                @endif
                                @if($url !== '#')
                                    <a href="{{ $url }}"
                                       class="btn btn-sm btn-outline-primary py-0 px-2"
                                       onclick="markRead('{{ $n->id }}')">View</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 d-block mb-2 opacity-25"></i>
                    No notifications yet.
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="mt-3 d-flex justify-content-center">
                {{ $notifications->links() }}
            </div>
        @endif

    </div>
</div>
@endsection

@section('scripts')
<script>
function markRead(id) {
    fetch('/clinician/notifications/' + id + '/read', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
        },
    });
}
</script>
@endsection
