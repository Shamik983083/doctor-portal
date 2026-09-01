@extends('layouts.admin')

@section('title', 'EHR Records')
@section('page-title', 'EHR Records')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Outbox</h6>
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['pending','sent','failed','disabled'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <input type="date" name="date" class="form-control form-control-sm" style="width:auto"
                   value="{{ request('date') }}" onchange="this.form.submit()">
            @if(request()->hasAny(['partner_id','status','date']))
                <a href="{{ route('admin.ehr-records.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0" style="padding:0!important;overflow:visible">
        <div class="table-responsive" style="overflow-x:auto;min-height:1px">
            <table class="table table-hover mb-0" style="font-size:.875rem">
                <thead class="table-light">
                    <tr>
                        <th>Case</th>
                        <th>Partner</th>
                        <th>Adapter</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Last Error</th>
                        <th>Sent</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                    @php
                        $badgeClass = match($record->status) {
                            'sent'     => 'bg-success',
                            'failed'   => 'bg-danger',
                            'pending'  => 'bg-warning text-dark',
                            default    => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            @if($record->case)
                                <a href="{{ route('admin.cases.show', $record->case->uuid) }}"
                                   class="font-monospace text-decoration-none text-body">
                                    {{ substr($record->case->uuid, 0, 8) }}&hellip;
                                </a>
                            @else
                                <span class="font-monospace text-muted">
                                    {{ substr($record->payload['source']['case_id'] ?? '?', 0, 8) }}&hellip;
                                </span>
                            @endif
                        </td>
                        <td>{{ $record->payload['company']['name'] ?? '—' }}</td>
                        <td><code class="text-body">{{ $record->adapter }}</code></td>
                        <td><span class="badge {{ $badgeClass }}">{{ $record->status }}</span></td>
                        <td class="font-variant-numeric">{{ $record->attempts }}</td>
                        <td>
                            @if($record->last_error)
                                <span class="text-danger text-truncate d-inline-block"
                                      style="max-width:200px" title="{{ $record->last_error }}">
                                    {{ $record->last_error }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($record->sent_at)
                                <span title="{{ $record->sent_at }}">{{ $record->sent_at->diffForHumans() }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $record->created_at->format('M j, H:i') }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('admin.ehr-records.show', $record->uuid) }}"
                               class="btn btn-sm btn-outline-secondary py-0">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No EHR records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($records->hasPages())
    <div class="card-footer">
        {{ $records->links() }}
    </div>
    @endif
</div>
@endsection
