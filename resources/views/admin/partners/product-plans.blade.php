@extends('layouts.admin')

@section('title', 'Product Plans — ' . $partner->name)
@section('page-title', 'Product Plans')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.partners.show', $partner->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to {{ $partner->name }}
    </a>
    <div>
        <h5 class="mb-0">{{ $partner->name }} — Product Plans</h5>
        <div class="text-muted small">Maps partner <code>product_key + month_frequency</code> to a portal offering</div>
    </div>
</div>

<div class="alert alert-info small d-flex gap-2">
    <i class="bi bi-info-circle mt-1 flex-shrink-0"></i>
    <div>
        <strong>What this does:</strong> Instead of knowing portal offering UUIDs, the partner sends
        <code>"product_key": "your-key"</code> + <code>"month_frequency": 3</code> in the case creation API.
        The portal resolves to the correct offering using the mapping below.
        <br>The <strong>legacy path</strong> (<code>offering_id</code> directly) remains fully supported — no existing integrations break.
        <br>After prescribing, the <code>prescription_written</code> webhook echoes <code>product_key</code> and
        <code>month_frequency</code> back so the partner can place the VRIO CRM order.
    </div>
</div>


<div class="row g-4">

    {{-- ── Create form ──────────────────────────────────────────────────────── --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Add Plan Variant</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.partners.product-plans.store', $partner->id) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Key <span class="text-danger">*</span></label>
                        <input type="text" name="product_key"
                               class="form-control @error('product_key') is-invalid @enderror"
                               value="{{ old('product_key') }}"
                               placeholder="e.g. glp1-weightloss"
                               required maxlength="120">
                        <div class="form-text">The partner's own identifier for this product. Use the exact string they send in the API.</div>
                        @error('product_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Month Frequency <span class="text-danger">*</span></label>
                        <select name="month_frequency" class="form-select @error('month_frequency') is-invalid @enderror" required>
                            <option value="">— Select duration —</option>
                            @foreach([1, 3, 4, 6, 12] as $freq)
                                <option value="{{ $freq }}" {{ old('month_frequency') == $freq ? 'selected' : '' }}>
                                    {{ $freq }} month{{ $freq > 1 ? 's' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">How many months this plan variant covers.</div>
                        @error('month_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Offering <span class="text-danger">*</span></label>
                        <select name="offering_id" class="form-select @error('offering_id') is-invalid @enderror" required>
                            <option value="">— Select offering —</option>
                            @foreach($accessibleOfferings as $o)
                                <option value="{{ $o->id }}" {{ old('offering_id') == $o->id ? 'selected' : '' }}>
                                    {{ $o->name }}{{ $o->internal_name ? ' (' . $o->internal_name . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">The portal offering that corresponds to this plan variant.</div>
                        @error('offering_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Label <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="label"
                               class="form-control"
                               value="{{ old('label') }}"
                               placeholder="e.g. 3-Month GLP-1 Plan"
                               maxlength="120">
                        <div class="form-text">Internal display label for this row. Not sent to the partner.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add Plan Variant
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Plan table ───────────────────────────────────────────────────────── --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Configured Plans</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary">{{ $grouped->sum(fn($g) => $g->count()) }} variant(s)</span>
                    @if($otherPartners->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#copyPlansModal">
                            <i class="bi bi-copy me-1"></i>Copy from partner
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($grouped->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-grid fs-2 d-block mb-2"></i>
                        No plans configured yet. Add the first variant using the form.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Key</th>
                                    <th class="text-center">Frequency</th>
                                    <th>Offering</th>
                                    <th>Label</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($grouped as $productKey => $plans)
                                    @foreach($plans as $i => $plan)
                                    <tr>
                                        @if($i === 0)
                                            <td class="align-middle fw-semibold" rowspan="{{ $plans->count() }}"
                                                style="border-right:2px solid var(--bs-border-color)">
                                                <code style="font-size:.82rem">{{ $productKey }}</code>
                                            </td>
                                        @endif
                                        <td class="text-center align-middle">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                                {{ $plan->month_frequency }}M
                                            </span>
                                        </td>
                                        <td class="align-middle small">
                                            {{ $plan->offering?->name ?? '—' }}
                                            @if($plan->offering?->internal_name)
                                                <span class="text-muted">({{ $plan->offering->internal_name }})</span>
                                            @endif
                                        </td>
                                        <td class="align-middle small text-muted">{{ $plan->label ?: '—' }}</td>
                                        <td class="text-end align-middle pe-3">
                                            <form method="POST"
                                                  action="{{ route('admin.partners.product-plans.destroy', [$partner->id, $plan->id]) }}"
                                                  onsubmit="return confirm('Delete plan \'{{ addslashes($productKey) }}\' ({{ $plan->month_frequency }}M)? Existing cases are unaffected.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-secondary border-0 rounded-0 small mb-0 py-2 px-3">
                        <i class="bi bi-shield-check me-1"></i>
                        Deleting a plan does <strong>not</strong> affect existing cases — they retain their
                        <code>product_key</code> snapshot and the webhook continues to echo it correctly.
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Copy plans modal --}}
@if($otherPartners->isNotEmpty())
<div class="modal fade" id="copyPlansModal" tabindex="-1" aria-labelledby="copyPlansModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyPlansModalLabel">
                    <i class="bi bi-copy me-2"></i>Copy plans from another partner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.partners.product-plans.copy', $partner->id) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Select a source partner. All their product plans will be copied to
                        <strong>{{ $partner->name }}</strong>, with the following rules:
                    </p>
                    <ul class="small text-muted mb-3">
                        <li>Plans that <strong>already exist</strong> on this partner are skipped.</li>
                        <li>Plans whose offering is <strong>not accessible</strong> to this partner are skipped.</li>
                        <li>Existing plans on this partner are <strong>never removed</strong>.</li>
                    </ul>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Source partner <span class="text-danger">*</span></label>
                        <select name="source_partner_id" class="form-select" required>
                            <option value="">— Select a partner —</option>
                            @foreach($otherPartners as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Only partners that have at least one configured plan will yield results.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Copy all eligible plans from the selected partner to {{ addslashes($partner->name) }}?')">
                        <i class="bi bi-copy me-1"></i>Copy plans
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
