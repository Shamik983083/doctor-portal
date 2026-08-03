@extends('layouts.admin')

@section('title', 'AI Instruction Sets')
@section('page-title', 'AI Instruction Sets')

@section('content')

<div class="row g-4">
    <div class="col-12 col-xl-10">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:36px;height:36px;background:#4361ee1a;">
                    <i class="bi bi-robot" style="color:#4361ee;font-size:1rem;"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-semibold">AI Instruction Sets</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">
                        Each context maps to an instruction set that guides how the AI drafts text.
                        Changes take effect immediately on the next draft request.
                    </p>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3" style="width:30%">Context</th>
                                <th class="py-3" style="width:15%">Status</th>
                                <th class="py-3" style="width:25%">Last Updated</th>
                                <th class="py-3 text-center" style="width:12%">Examples</th>
                                <th class="py-3 pe-4 text-end" style="width:18%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contextLabels as $context => $label)
                                @php $set = $sets[$context] ?? null; @endphp
                                <tr>
                                    <td class="ps-4 py-3 align-middle">
                                        <div class="fw-semibold">{{ $label }}</div>
                                        <code class="text-muted" style="font-size:.7rem;">{{ $context }}</code>
                                    </td>
                                    <td class="py-3 align-middle">
                                        @if($set)
                                            <span class="badge rounded-pill"
                                                  style="background:#d1fae5;color:#065f46;font-size:.72rem;font-weight:600;">
                                                Active &middot; v{{ $set->version }}
                                            </span>
                                        @else
                                            <span class="badge rounded-pill"
                                                  style="background:#fee2e2;color:#991b1b;font-size:.72rem;font-weight:600;">
                                                Not configured
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 align-middle text-muted" style="font-size:.8rem;">
                                        @if($set && $set->updatedBy)
                                            <span>{{ $set->updatedBy->name }}</span><br>
                                            <span class="text-muted" style="font-size:.72rem;">{{ $set->updated_at->diffForHumans() }}</span>
                                        @elseif($set)
                                            <span style="font-size:.72rem;">{{ $set->updated_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 align-middle text-center">
                                        @if($set)
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"
                                                  style="font-size:.72rem;">
                                                {{ $set->examples_count }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pe-4 align-middle text-end">
                                        <a href="{{ route('admin.ai.edit', $context) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1"></i>
                                            {{ $set ? 'Edit' : 'Configure' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
