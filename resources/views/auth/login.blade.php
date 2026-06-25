<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Doctor Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a2035 0%, #1e3a5f 100%); min-height: 100vh; display:flex; align-items:center; }
        .login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.3); border:0; }
        .login-logo { font-size: 2.5rem; color:#dc3545; }
    </style>
</head>
<body>
<div class="container" style="max-width:420px;">
    <div class="card login-card p-4">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo mb-2"><i class="bi bi-heart-pulse-fill"></i></div>
                <h4 class="fw-bold">Doctor Portal</h4>
                <p class="text-muted small">Telehealth Platform</p>
            </div>
            @if($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control form-control-lg" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn btn-danger btn-lg w-100">Sign In</button>
            </form>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>
