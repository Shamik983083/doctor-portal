@extends('layouts.admin')

@section('title', 'Cases')
@section('page-title', 'All Cases')

@section('content')
<x-ma-styles />
<div class="ma-surface">

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="ma-eyebrow">Case management</div>
                    <div class="ma-title">All cases</div>
                    <div class="ma-sub">Full case pipeline across all partners and clinicians.</div>
                </div>
                <div class="ma-legend align-self-center">
                    <span class="ma-pill red"><span class="ma-dot"></span>Red · review carefully</span>
                    <span class="ma-pill yellow"><span class="ma-dot"></span>Yellow · closer look</span>
                    <span class="ma-pill green"><span class="ma-dot"></span>Green · routine</span>
                </div>
            </div>
            <form class="row g-2 align-items-center" method="GET">
                <div class="col">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search patient name…" value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="triage" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Triage</option>
                        @foreach(['red' => 'Red', 'yellow' => 'Yellow', 'green' => 'Green'] as $val => $lbl)
                            <option value="{{ $val }}" {{ request('triage') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        @foreach(['created','waiting','support','assigned','approved','processing','completed','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="partner_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Partners</option>
                        @foreach($partners as $p)
                            <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="clinician_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Clinicians</option>
                        @foreach($clinicians as $c)
                            <option value="{{ $c->id }}" {{ request('clinician_id') == $c->id ? 'selected' : '' }}>{{ $c->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    @if(request()->anyFilled(['search','triage','status','partner_id','clinician_id']))
                        <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Triage</th>
                            <th>Patient</th>
                            <th>Partner</th>
                            <th>Clinician</th>
                            <th>Offerings</th>
                            <th>Status</th>
                            <th>Support</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                        <tr>
                            <td>
                                @if($case->triage)
                                    <span class="ma-pill {{ $case->triage }}"><span class="ma-dot"></span>{{ ucfirst($case->triage) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $case->patient?->full_name ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $case->patient_state ?? $case->patient?->state ?? '—' }}</small>
                            </td>
                            <td><small>{{ $case->partner?->name ?? '—' }}</small></td>
                            <td>
                                @if($case->clinician)
                                    <small>{{ $case->clinician->full_name }}</small>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if($case->caseOfferings->isNotEmpty())
                                    <span class="ma-pill neutral">{{ $case->caseOfferings->first()->offering?->name ?? '?' }}</span>
                                    @if($case->caseOfferings->count() > 1)
                                        <span class="text-muted small">+{{ $case->caseOfferings->count() - 1 }} more</span>
                                    @endif
                                @endif
                            </td>
                            <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                            <td>
                                @if($case->support_at)
                                    <i class="bi bi-check-circle-fill text-warning" title="In support at {{ $case->support_at->format('M d H:i') }}"></i>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ $case->created_at->diffForHumans() }}</small></td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <form method="POST" action="{{ route('admin.cases.destroy', $case->uuid) }}" onsubmit="return confirm('Delete this case? This cannot be undone.')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases found.</td></tr>
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
