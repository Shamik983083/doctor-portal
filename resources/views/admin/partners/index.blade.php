@extends('layouts.admin')

@section('title', 'Partners')
@section('page-title', 'Partners')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Partners</h6>
        <a href="{{ route('admin.partners.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Partner</a>
    </div>
    <div class="card-header bg-light border-top-0">
        <form class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or email..." value="{{ request('search') }}">
            <select name="status" class="form-select form-select-sm w-auto">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
                <option value="suspended" {{ request('status')=='suspended'?'selected':'' }}>Suspended</option>
                <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Patients</th>
                        <th>Cases</th>
                        <th>Status</th>
                        <th>Client ID</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partners as $partner)
                    <tr>
                        <td><strong>{{ $partner->name }}</strong></td>
                        <td>{{ $partner->email }}</td>
                        <td>{{ $partner->patients_count }}</td>
                        <td>{{ $partner->cases_count }}</td>
                        <td>
                            <span class="badge {{ match($partner->status) { 'active'=>'bg-success','suspended'=>'bg-warning text-dark',default=>'bg-secondary' } }}">
                                {{ ucfirst($partner->status) }}
                            </span>
                        </td>
                        <td><code class="small">{{ $partner->client_id ?? '—' }}</code></td>
                        <td>
                            <a href="{{ route('admin.partners.show', $partner->id) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="{{ route('admin.partners.edit', $partner->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No partners yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($partners->hasPages())
    <div class="card-footer">{{ $partners->links() }}</div>
    @endif
</div>
@endsection
