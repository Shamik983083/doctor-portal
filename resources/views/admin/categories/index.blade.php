@extends('layouts.admin')

@section('title', 'Offering Categories')
@section('page-title', 'Offering Categories')

@section('content')

{{-- Routing v2 made accepted categories a hard block on which doctor can take a
     case, so a category is only half-created here: until doctors are ticked into
     it, nobody accepts it and cases in it will not route. They land visibly in
     the exceptions queue rather than disappearing, but the fix is on the doctor's
     screen, and this is where somebody will be standing when they need to know. --}}
<div class="alert alert-secondary small d-flex align-items-start gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        <strong>A new category is accepted by no doctor until you tick them in.</strong>
        Cases in a category nobody accepts cannot be routed, and appear in
        <a href="{{ route('admin.routing.exceptions') }}">Routing Exceptions</a> as
        "Doctor does not accept this product category". Set who takes what on each
        <a href="{{ route('admin.clinicians.index') }}">doctor's profile</a>.
    </div>
</div>

<div class="row g-4">

    {{-- Create form --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">New Category</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}"
                               placeholder="e.g. Weight Loss"
                               required maxlength="150">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Optional — shown to partners when choosing a category">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Check-in questionnaire
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <select name="check_in_questionnaire_id" class="form-select form-select-sm">
                            <option value="">— None (fall back to initial intake) —</option>
                            @foreach($checkInQuestionnaires as $q)
                                <option value="{{ $q->id }}" {{ old('check_in_questionnaire_id') == $q->id ? 'selected' : '' }}>
                                    {{ $q->name }}{{ $q->partner ? ' (' . $q->partner->name . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Used for refill cases in this category when no per-offering check-in questionnaire is set. Blank = initial intake is used as fallback.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Create Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Category list --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">All Categories</h6>
                <span class="badge bg-secondary">{{ $categories->count() }}</span>
            </div>
            <div class="card-body p-0">
                @if($categories->count())
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Check-in Questionnaire</th>
                            <th class="text-center">Offerings</th>
                            <th class="text-center">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                        <tr>
                            <td class="fw-semibold align-middle">{{ $cat->name }}</td>
                            <td class="text-muted small align-middle" style="max-width:200px">
                                {{ Str::limit($cat->description, 60, '…') ?: '—' }}
                            </td>
                            <td class="align-middle" style="min-width:200px">
                                <form method="POST" action="{{ route('admin.categories.update-check-in', $cat->id) }}" class="d-flex gap-1 align-items-center">
                                    @csrf @method('PATCH')
                                    <select name="check_in_questionnaire_id" class="form-select form-select-sm"
                                            style="font-size:.8rem" onchange="this.form.submit()">
                                        <option value="">— None —</option>
                                        @foreach($checkInQuestionnaires as $q)
                                            <option value="{{ $q->id }}" {{ $cat->check_in_questionnaire_id == $q->id ? 'selected' : '' }}>
                                                {{ Str::limit($q->name, 32) }}{{ $q->partner ? ' (' . $q->partner->name . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge bg-light text-dark border">{{ $cat->offerings_count }}</span>
                            </td>
                            <td class="text-center align-middle">
                                <form method="POST" action="{{ route('admin.categories.toggle', $cat->id) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="badge border-0 {{ $cat->is_active ? 'bg-success' : 'bg-secondary' }}"
                                            style="cursor:pointer">
                                        {{ $cat->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end align-middle pe-3">
                                @if($cat->offerings_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}"
                                      onsubmit="return confirm('Delete category \'{{ addslashes($cat->name) }}\'?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @else
                                <span class="text-muted small" title="Has offerings attached">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-tags fs-2 d-block mb-2"></i>
                    No categories yet. Create your first one.
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
