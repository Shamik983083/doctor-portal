@extends('layouts.admin')

@section('title', 'New Partner')
@section('page-title', 'New Partner')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header"><h6 class="mb-0">Partner Details</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.partners.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Website</label>
                    <input type="url" name="website" class="form-control" value="{{ old('website') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Partner & Generate API Credentials</button>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
