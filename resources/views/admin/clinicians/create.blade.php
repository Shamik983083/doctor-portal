@extends('layouts.admin')

@section('title', 'Add Clinician')
@section('page-title', 'Add Clinician')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header"><h6 class="mb-0">Clinician Details</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.clinicians.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Credentials</label>
                    <select name="credentials" class="form-select">
                        <option value="">Select</option>
                        @foreach(['MD','DO','NP','PA'] as $c)
                        <option value="{{ $c }}" {{ old('credentials')===$c?'selected':'' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">NPI Number</label>
                    <input type="text" name="npi" class="form-control" value="{{ old('npi') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">License State</label>
                    <input type="text" name="license_state" class="form-control" maxlength="2" placeholder="CA" value="{{ old('license_state') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Specialty</label>
                <input type="text" name="specialty" class="form-control" value="{{ old('specialty') }}">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Clinician</button>
                <a href="{{ route('admin.clinicians.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
