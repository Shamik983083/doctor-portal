<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status === 'success' ? 'Submitted Successfully' : 'Not Eligible' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        @media (max-width: 575.98px) {
            .container { padding-left: 1rem; padding-right: 1rem; }
            .card { padding: 2rem 1.25rem !important; }
        }
    </style>
</head>
<body>
<div class="container py-5 text-center" style="max-width:520px">
    <div class="card shadow-sm border-0 p-5">
        @if($status === 'success')
            <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
            <h4 class="mt-3 fw-semibold">All Done!</h4>
        @else
            <i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i>
            <h4 class="mt-3 fw-semibold">Not Eligible</h4>
        @endif
        <p class="text-muted mt-2 mb-0">{{ $message }}</p>
    </div>
</div>

<script>
// Notify parent window for iFrame embed integrations
(function () {
    try {
        var payload = {!! $postMessagePayload !!};
        window.parent.postMessage(payload, '*');
    } catch (e) {}
})();
</script>
</body>
</html>
