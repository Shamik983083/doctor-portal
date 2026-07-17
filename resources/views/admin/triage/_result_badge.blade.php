@if($result === 'red')
<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
    <i class="bi bi-exclamation-circle me-1"></i>Red
</span>
@else
<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
    <i class="bi bi-exclamation-triangle me-1"></i>Yellow
</span>
@endif
