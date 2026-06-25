@extends('layouts.admin')

@section('title', 'Edit Partner')
@section('page-title', "Edit: {$partner->name}")

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header"><h6 class="mb-0">Edit Partner</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.partners.update', $partner->id) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Company Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $partner->phone) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Website</label>
                    <input type="url" name="website" class="form-control" value="{{ old('website', $partner->website) }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    @foreach(['active','suspended','inactive'] as $s)
                    <option value="{{ $s }}" {{ $partner->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $partner->description) }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
