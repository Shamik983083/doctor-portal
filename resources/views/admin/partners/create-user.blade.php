@extends('layouts.app')
@php $pageTitle = "Add User — {$partner->name}"; @endphp
@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.partners.show', $partner->id) }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to {{ $partner->name }}
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold">Create Partner Portal User</h6>
                <div class="text-muted small mt-1">This user will be able to log in at <code>/login</code> and access the Partner Portal for <strong>{{ $partner->name }}</strong>.</div>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.partners.users.store', $partner->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="Jane Smith" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" placeholder="jane@partner.com" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               placeholder="Minimum 8 characters" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i> Create User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
