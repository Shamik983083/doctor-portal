<div class="d-flex gap-1 justify-content-end">
    <button type="button"
            class="btn btn-sm btn-outline-primary btn-edit-rule"
            data-route="{{ route('admin.triage-rules.update', $rule->id) }}"
            data-type="{{ $rule->type }}"
            data-operator="{{ $rule->operator }}"
            data-value="{{ $rule->value }}"
            data-result="{{ $rule->triage_result }}"
            data-label="{{ $rule->label }}"
            data-active="{{ $rule->is_active ? '1' : '0' }}"
            title="Edit rule">
        <i class="bi bi-pencil"></i>
    </button>
    <form method="POST"
          action="{{ route('admin.triage-rules.destroy', $rule->id) }}"
          onsubmit="return confirm('Delete this triage rule? Cases already triaged will not be affected until re-triaged.')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete rule">
            <i class="bi bi-trash3"></i>
        </button>
    </form>
</div>
