<form method="POST"
      action="{{ route('admin.triage-rules.toggle', $rule->id) }}"
      style="display:inline;">
    @csrf @method('PATCH')
    <button type="submit"
            class="btn btn-sm {{ $rule->is_active ? 'btn-success' : 'btn-secondary' }}"
            title="{{ $rule->is_active ? 'Deactivate rule' : 'Activate rule' }}"
            style="padding:2px 8px;font-size:.72rem;">
        <i class="bi bi-{{ $rule->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
        {{ $rule->is_active ? 'Active' : 'Inactive' }}
    </button>
</form>
