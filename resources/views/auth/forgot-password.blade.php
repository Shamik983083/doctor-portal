<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Doctor Portal</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"></noscript>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --panel-bg:#091929; --panel-text:#ffffff; --panel-body:rgba(210,228,238,.60); --panel-rule:rgba(210,228,238,.10);
            --accent:#00B4D8; --accent-dark:#0097B8; --accent-ring:rgba(0,180,216,.22); --accent-glow:rgba(0,180,216,.28);
            --form-bg:#F5F8FB; --form-text:#0D1B2A; --form-muted:#566E7D;
            --input-bg:#ffffff; --input-border:#C3D2DB; --label-color:#3B5365; --footer-color:#8DAAB7;
            --error-text:#b91c1c; --error-bg:rgba(185,28,28,.06); --error-border:rgba(185,28,28,.18);
            --sent-bg:#f0fdf4; --sent-border:#bbf7d0; --sent-text:#166534;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
                --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
                --error-text:#fca5a5; --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25);
                --sent-bg:rgba(6,95,70,.12); --sent-border:rgba(34,197,94,.2); --sent-text:#86efac;
            }
        }
        :root[data-theme="dark"] {
            --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
            --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
            --error-text:#fca5a5; --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25);
            --sent-bg:rgba(6,95,70,.12); --sent-border:rgba(34,197,94,.2); --sent-text:#86efac;
        }
        :root[data-theme="light"] {
            --form-bg:#F5F8FB; --form-text:#0D1B2A; --form-muted:#566E7D;
            --input-bg:#ffffff; --input-border:#C3D2DB; --label-color:#3B5365; --footer-color:#8DAAB7;
            --error-text:#b91c1c; --error-bg:rgba(185,28,28,.06); --error-border:rgba(185,28,28,.18);
            --sent-bg:#f0fdf4; --sent-border:#bbf7d0; --sent-text:#166534;
        }

        html { height:100%; }
        body {
            height:100%;
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;
            -webkit-font-smoothing:antialiased;
            background:var(--form-bg);
        }

        .login-wrap { display:flex;min-height:100vh; }

        .brand-panel {
            flex:0 0 54%;background:var(--panel-bg);
            display:flex;flex-direction:column;
            padding:clamp(1.5rem,4vw,2.75rem) clamp(1.5rem,5vw,4rem);
            position:relative;overflow:hidden;
        }
        .brand-logo { display:flex;align-items:center;gap:.7rem;text-decoration:none;user-select:none;flex-shrink:0; }
        .brand-cross { position:relative;width:28px;height:28px;flex-shrink:0; }
        .brand-cross::before,.brand-cross::after { content:'';position:absolute;background:var(--accent);border-radius:2px; }
        .brand-cross::before { width:7px;height:100%;left:50%;transform:translateX(-50%); }
        .brand-cross::after  { width:100%;height:7px;top:50%;transform:translateY(-50%); }
        .brand-wordmark { font-size:clamp(.65rem,1.5vw,.72rem);font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#fff;line-height:1; }
        .brand-wordmark em { font-style:normal;color:var(--accent); }

        .brand-body { flex:1;display:flex;flex-direction:column;justify-content:center;padding-bottom:5.5rem; }
        .brand-eyebrow { font-size:clamp(.6rem,1.2vw,.67rem);font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:var(--accent);opacity:.85;margin-bottom:clamp(.75rem,2vw,1.2rem); }
        .brand-headline { font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.6rem,3vw,2.55rem);font-weight:400;line-height:1.3;color:var(--panel-text);text-wrap:balance;margin-bottom:clamp(1.25rem,2.5vw,2rem); }

        .brand-list { list-style:none;border-top:1px solid var(--panel-rule); }
        .brand-list li { display:flex;align-items:flex-start;gap:.9rem;padding:clamp(.65rem,1.5vw,.9rem) 0;border-bottom:1px solid var(--panel-rule);color:var(--panel-body);font-size:clamp(.73rem,1.3vw,.78rem);line-height:1.55; }
        .brand-list li i { color:var(--accent);font-size:.88rem;opacity:.75;flex-shrink:0;margin-top:.15rem; }

        .ecg-wrap { position:absolute;bottom:0;left:0;right:0;height:88px;pointer-events:none; }
        #ecgCanvas { display:block;width:100%;height:100%; }

        .auth-panel { flex:1;background:var(--form-bg);display:flex;align-items:center;justify-content:center;padding:clamp(2rem,5vw,3rem) clamp(1.25rem,4vw,2rem); }
        .auth-box { width:100%;max-width:360px; }

        .auth-kicker { font-size:.67rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--form-muted);margin-bottom:.35rem; }
        .auth-title { font-size:clamp(1.35rem,3vw,1.55rem);font-weight:700;letter-spacing:-.025em;color:var(--form-text);margin-bottom:.45rem; }
        .auth-sub { font-size:.84rem;color:var(--form-muted);margin-bottom:1.6rem;line-height:1.6; }

        .form-alert {
            display:flex;align-items:flex-start;gap:.5rem;padding:.7rem .9rem;
            background:var(--error-bg);border:1px solid var(--error-border);
            border-radius:8px;font-size:.81rem;color:var(--error-text);margin-bottom:1.1rem;line-height:1.45;
        }
        .form-alert i { flex-shrink:0;margin-top:.1rem; }

        /* ── Email-sent success card ── */
        .sent-card {
            background:var(--sent-bg);border:1px solid var(--sent-border);
            border-radius:12px;padding:24px 20px;text-align:center;
            margin-bottom:1.5rem;
        }
        .sent-icon {
            width:56px;height:56px;margin:0 auto 14px;
            background:rgba(0,180,216,.1);border-radius:50%;
            display:flex;align-items:center;justify-content:center;
        }
        .sent-icon i { font-size:1.4rem;color:var(--accent); }
        .sent-card h3 { font-size:1rem;font-weight:700;color:var(--form-text);margin-bottom:6px; }
        .sent-card p { font-size:.83rem;color:var(--form-muted);line-height:1.6; }
        .sent-steps {
            margin-top:16px;text-align:left;list-style:none;
            border-top:1px solid var(--sent-border);padding-top:14px;
            display:flex;flex-direction:column;gap:8px;
        }
        .sent-steps li { display:flex;align-items:flex-start;gap:8px;font-size:.78rem;color:var(--form-muted);line-height:1.5; }
        .sent-step-num { flex-shrink:0;width:18px;height:18px;background:var(--accent);border-radius:50%;color:#fff;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px; }

        .field { margin-bottom:1rem; }
        .field > label { display:block;font-size:.67rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--label-color);margin-bottom:.42rem; }

        .input-wrap { position:relative;display:flex;align-items:center; }
        .input-icon { position:absolute;left:.8rem;color:var(--label-color);font-size:.8rem;pointer-events:none;opacity:.65; }
        .input-wrap input {
            flex:1;font-size:max(.875rem,16px);font-family:inherit;
            color:var(--form-text);background:var(--input-bg);
            border:1.5px solid var(--input-border);border-radius:8px;
            padding:.72rem .8rem .72rem 2.35rem;
            outline:none;-webkit-appearance:none;appearance:none;
            transition:border-color .12s,box-shadow .12s;min-height:46px;
        }
        .input-wrap input:focus { border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-ring); }
        .input-wrap input::placeholder { color:var(--form-muted);opacity:.45; }

        .btn-submit {
            display:block;width:100%;padding:.9rem 1rem;min-height:50px;
            background:var(--accent);color:#fff;border:none;border-radius:8px;
            font-size:.9rem;font-family:inherit;font-weight:600;letter-spacing:.03em;
            cursor:pointer;transition:background .14s,box-shadow .14s,transform .08s;
            margin-top:1.25rem;
        }
        .btn-submit:hover:not(:disabled) { background:var(--accent-dark);box-shadow:0 4px 18px var(--accent-glow); }
        .btn-submit:active:not(:disabled) { transform:translateY(1px); }
        .btn-submit:disabled { opacity:.5;cursor:not-allowed; }

        .auth-back { display:flex;align-items:center;justify-content:center;gap:.3rem;margin-top:1.25rem;font-size:.78rem;color:var(--form-muted);text-decoration:none;transition:color .12s; }
        .auth-back:hover { color:var(--accent); }

        .auth-security { margin-top:1.5rem;display:flex;align-items:center;justify-content:center;gap:.35rem;font-size:.7rem;color:var(--footer-color);letter-spacing:.02em; }

        @media (max-width:820px) {
            .login-wrap { flex-direction:column; }
            .brand-panel { flex:none;padding:1.5rem 1.5rem 3.5rem;min-height:auto; }
            .brand-body { justify-content:flex-start;padding-top:1.25rem;padding-bottom:0;flex:none; }
            .brand-headline { font-size:clamp(1.3rem,4vw,1.65rem);margin-bottom:0; }
            .brand-list { display:none; }
            .ecg-wrap { height:72px; }
            .auth-panel { align-items:flex-start;padding:2rem 1.5rem 3rem; }
            .auth-box { max-width:420px; }
        }
        @media (max-width:640px) {
            .brand-panel { padding:1.25rem 1.25rem 3rem; }
            .brand-eyebrow { display:none; }
            .brand-headline { font-size:1.25rem; }
            .ecg-wrap { height:60px; }
            .auth-panel { padding:1.75rem 1.25rem 2.5rem; }
            .auth-box { max-width:100%; }
        }
        @media (prefers-reduced-motion:reduce) {
            .btn-submit,.input-wrap input { transition:none; }
        }
    </style>
</head>
<body>
<div class="login-wrap">

    <aside class="brand-panel">
        <a class="brand-logo" href="{{ route('login') }}">
            <div class="brand-cross" aria-hidden="true"></div>
            <span class="brand-wordmark">Doctor <em>Portal</em></span>
        </a>
        <div class="brand-body">
            <p class="brand-eyebrow">Account Recovery</p>
            <h1 class="brand-headline">We'll get you<br>back in.</h1>

            <ul class="brand-list">
                <li><i class="bi bi-envelope-check" aria-hidden="true"></i>Reset link sent to your registered email</li>
                <li><i class="bi bi-clock-history" aria-hidden="true"></i>Link expires after 60 minutes for security</li>
                <li><i class="bi bi-shield-check" aria-hidden="true"></i>We never expose which emails are registered</li>
            </ul>
        </div>
        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-box">

            @if(session('status'))
            {{-- ── Email sent success state ── --}}
            <div class="sent-card" role="status">
                <div class="sent-icon">
                    <i class="bi bi-envelope-check-fill" aria-hidden="true"></i>
                </div>
                <h3>Check your inbox</h3>
                <p>If that email is registered, a reset link is on its way. It may take a moment to arrive.</p>
                <ol class="sent-steps" aria-label="Next steps">
                    <li>
                        <span class="sent-step-num">1</span>
                        Open the email from Doctor Portal
                    </li>
                    <li>
                        <span class="sent-step-num">2</span>
                        Click the reset link (valid for 60 minutes)
                    </li>
                    <li>
                        <span class="sent-step-num">3</span>
                        Choose a strong new password
                    </li>
                </ol>
            </div>

            <p style="font-size:.8rem;color:var(--form-muted);text-align:center;line-height:1.6;margin-bottom:1.25rem;">
                Didn't receive it? Check your spam folder, or
                <a href="{{ route('password.request') }}" style="color:var(--accent);text-decoration:none;font-weight:500;">try again</a>.
            </p>

            @else
            {{-- ── Request form ── --}}
            <p class="auth-kicker">Account Recovery</p>
            <h2 class="auth-title">Forgot your password?</h2>
            <p class="auth-sub">Enter your email address and we'll send you a secure reset link.</p>

            @if($errors->any())
            <div class="form-alert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" id="fpForm">
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon" aria-hidden="true"></i>
                        <input type="email" id="email" name="email"
                               value="{{ old('email') }}"
                               placeholder="you@example.com"
                               autocomplete="email"
                               required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Send Reset Link
                </button>
            </form>
            @endif

            <a href="{{ route('login') }}" class="auth-back">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Back to Sign In
            </a>

            <p class="auth-security">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                Encrypted &nbsp;&middot;&nbsp; HIPAA compliant
            </p>

        </div>
    </main>

</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('fpForm');
    var btn  = document.getElementById('submitBtn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled    = true;
            btn.textContent = 'Sending…';
        });
    }

    /* ── ECG canvas ── */
    var canvas = document.getElementById('ecgCanvas');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        var reduced = window.matchMedia('(prefers-reduced-motion:reduce)').matches;
        function resize(){ canvas.width=canvas.clientWidth; canvas.height=canvas.clientHeight; }
        resize(); window.addEventListener('resize', resize);
        var pts=[[0,0],[.05,0],[.09,-.07],[.13,0],[.24,0],[.27,.09],[.32,-.95],[.36,.20],[.40,0],[.46,0],[.51,-.06],[.58,-.22],[.65,-.06],[.71,0],[1,0]];
        var CW=270,AMP=26,BASE=.5,SPD=.55,off=0;
        function draw(){var W=canvas.width,H=canvas.height;ctx.clearRect(0,0,W,H);var bY=H*BASE;var g=ctx.createLinearGradient(0,0,W,0);g.addColorStop(0,'rgba(0,180,216,0)');g.addColorStop(.07,'rgba(0,180,216,.58)');g.addColorStop(.93,'rgba(0,180,216,.58)');g.addColorStop(1,'rgba(0,180,216,0)');ctx.strokeStyle=g;ctx.lineWidth=1.8;ctx.shadowColor='rgba(0,180,216,.42)';ctx.shadowBlur=10;ctx.lineJoin='round';ctx.lineCap='round';var sX=-(off%CW),nC=Math.ceil(W/CW)+2;ctx.beginPath();var f=true;for(var c=-1;c<nC;c++){for(var p=0;p<pts.length;p++){var x=sX+(c+pts[p][0])*CW,y=bY+pts[p][1]*AMP;if(f){ctx.moveTo(x,y);f=false;}else ctx.lineTo(x,y);}}ctx.stroke();if(!reduced){off+=SPD;requestAnimationFrame(draw);}}
        draw();
    }
}());
</script>

</body>
</html>
