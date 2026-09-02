@extends('layouts.admin')

@section('title', "Sub-Storefronts — {$partner->name}")
@section('page-title', "Sub-Storefronts: {$partner->name}")

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('admin.partners.show', $partner->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Partner
    </a>
    <a href="{{ route('admin.partners.sub-storefronts.create', $partner->id) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Sub-Storefront
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('healthie_warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('healthie_warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Sub-Storefronts</h6>
        <span class="badge bg-secondary">{{ $subStorefronts->count() }}</span>
    </div>

    @if($subStorefronts->isEmpty())
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-diagram-3 fs-1 d-block mb-3 opacity-25"></i>
            <p class="mb-2">No sub-storefronts yet.</p>
            <a href="{{ route('admin.partners.sub-storefronts.create', $partner->id) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Create First Sub-Storefront
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>UUID</th>
                        <th>Healthie Org</th>
                        <th>Status</th>
                        <th>Healthie</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subStorefronts as $sf)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $sf->name }}</div>
                            <div class="text-muted" style="font-size:.78rem">{{ $sf->slug }}</div>
                        </td>
                        <td>
                            <code class="small">{{ $sf->uuid }}</code>
                        </td>
                        <td>
                            @if($sf->healthie_organization_id)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
                                    {{ $sf->healthie_organization_id }}
                                </span>
                            @else
                                <span class="text-muted small">Not provisioned</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ match($sf->status) {
                                'active'    => 'bg-success',
                                'suspended' => 'bg-warning text-dark',
                                default     => 'bg-secondary',
                            } }}">
                                {{ ucfirst($sf->status) }}
                            </span>
                        </td>
                        <td>
                            @if($sf->healthie_is_enabled && $sf->healthie_sandbox_validated)
                                <span class="badge bg-success" title="Enabled & validated"><i class="bi bi-check-circle-fill"></i> Live</span>
                            @elseif($sf->healthie_is_enabled)
                                <span class="badge bg-warning text-dark" title="Enabled but not sandbox-validated"><i class="bi bi-exclamation-circle-fill"></i> Pending</span>
                            @else
                                <span class="badge bg-secondary" title="Not enabled"><i class="bi bi-dash-circle"></i> Off</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.partners.sub-storefronts.edit', [$partner->id, $sf->id]) }}"
                               class="btn btn-outline-primary btn-sm py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.partners.sub-storefronts.destroy', [$partner->id, $sf->id]) }}"
                                  class="d-inline" onsubmit="return confirm('Delete sub-storefront \'{{ addslashes($sf->name) }}\'? This cannot be undone. The Healthie sub-org must be removed manually.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
