<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Doctor Portal</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"></noscript>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --panel-bg:      #091929;
            --panel-text:    #FFFFFF;
            --panel-body:    rgba(210, 228, 238, 0.60);
            --panel-rule:    rgba(210, 228, 238, 0.10);
            --accent:        #00B4D8;
            --accent-dark:   #0097B8;
            --accent-ring:   rgba(0, 180, 216, 0.22);
            --accent-glow:   rgba(0, 180, 216, 0.28);
            --form-bg:       #F5F8FB;
            --form-text:     #0D1B2A;
            --form-muted:    #566E7D;
            --input-bg:      #FFFFFF;
            --input-border:  #C3D2DB;
            --label-color:   #3B5365;
            --footer-color:  #8DAAB7;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --form-bg:      #0B1D2D;
                --form-text:    #D6E7EF;
                --form-muted:   #6E8F9F;
                --input-bg:     #0F2233;
                --input-border: #1B3549;
                --label-color:  #7DA0B2;
                --footer-color: #3C5A6A;
            }
        }
        :root[data-theme="dark"] {
            --form-bg:      #0B1D2D;
            --form-text:    #D6E7EF;
            --form-muted:   #6E8F9F;
            --input-bg:     #0F2233;
            --input-border: #1B3549;
            --label-color:  #7DA0B2;
            --footer-color: #3C5A6A;
        }
        :root[data-theme="light"] {
            --form-bg:      #F5F8FB;
            --form-text:    #0D1B2A;
            --form-muted:   #566E7D;
            --input-bg:     #FFFFFF;
            --input-border: #C3D2DB;
            --label-color:  #3B5365;
            --footer-color: #8DAAB7;
        }

        html { height: 100%; }
        body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-wrap { display: flex; min-height: 100vh; }

        /* ── Brand panel ── */
        .brand-panel {
            flex: 0 0 54%;
            background: var(--panel-bg);
            display: flex;
            flex-direction: column;
            padding: clamp(1.5rem, 4vw, 2.75rem) clamp(1.5rem, 5vw, 4rem);
            position: relative;
            overflow: hidden;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: .7rem;
            text-decoration: none;
            user-select: none;
            flex-shrink: 0;
        }

        .brand-cross { position: relative; width: 28px; height: 28px; flex-shrink: 0; }
        .brand-cross::before,
        .brand-cross::after {
            content: '';
            position: absolute;
            background: var(--accent);
            border-radius: 2px;
        }
        .brand-cross::before { width: 7px; height: 100%; left: 50%; transform: translateX(-50%); }
        .brand-cross::after  { width: 100%; height: 7px; top: 50%; transform: translateY(-50%); }

        .brand-wordmark {
            font-size: clamp(.65rem, 1.5vw, .72rem);
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1;
        }
        .brand-wordmark em { font-style: normal; color: var(--accent); }

        .brand-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-bottom: 5.5rem;
        }

        .brand-eyebrow {
            font-size: clamp(.6rem, 1.2vw, .67rem);
            font-weight: 600;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--accent);
            opacity: .85;
            margin-bottom: clamp(.75rem, 2vw, 1.2rem);
        }

        .brand-headline {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: clamp(1.6rem, 3vw, 2.55rem);
            font-weight: 400;
            line-height: 1.3;
            color: var(--panel-text);
            text-wrap: balance;
            margin-bottom: clamp(1.5rem, 3.5vw, 2.75rem);
        }

        .brand-list { list-style: none; border-top: 1px solid var(--panel-rule); }
        .brand-list li {
            display: flex;
            align-items: flex-start;
            gap: .9rem;
            padding: clamp(.65rem, 1.5vw, .9rem) 0;
            border-bottom: 1px solid var(--panel-rule);
            color: var(--panel-body);
            font-size: clamp(.75rem, 1.4vw, .8rem);
            line-height: 1.55;
        }
        .brand-list li i {
            color: var(--accent);
            font-size: .88rem;
            opacity: .75;
            flex-shrink: 0;
            margin-top: .18rem;
        }

        .ecg-wrap {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 88px;
            pointer-events: none;
        }
        #ecgCanvas { display: block; width: 100%; height: 100%; }

        /* ── Auth panel ── */
        .auth-panel {
            flex: 1;
            background: var(--form-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(2rem, 5vw, 3rem) clamp(1.25rem, 4vw, 2rem);
            transition: background .2s;
        }

        .auth-box { width: 100%; max-width: 330px; }

        .auth-kicker {
            font-size: clamp(.62rem, 1.3vw, .67rem);
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--form-muted);
            margin-bottom: .38rem;
        }

        .auth-title {
            font-size: clamp(1.35rem, 3vw, 1.55rem);
            font-weight: 700;
            letter-spacing: -.025em;
            color: var(--form-text);
            margin-bottom: .5rem;
        }

        .auth-subtitle {
            font-size: .8rem;
            color: var(--form-muted);
            margin-bottom: clamp(1.25rem, 3vw, 1.75rem);
            line-height: 1.5;
        }

        /* Alerts */
        .form-alert {
            display: flex;
            align-items: center;
            gap: .45rem;
            padding: .6rem .8rem;
            background: rgba(185, 28, 28, .07);
            border: 1px solid rgba(185, 28, 28, .18);
            border-radius: 6px;
            font-size: .8rem;
            color: #b91c1c;
            margin-bottom: 1.1rem;
            line-height: 1.4;
        }
        .form-alert i { flex-shrink: 0; }

        .form-success {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            padding: .7rem .85rem;
            background: rgba(4, 120, 87, .07);
            border: 1px solid rgba(4, 120, 87, .18);
            border-radius: 6px;
            font-size: .8rem;
            color: #065f46;
            margin-bottom: 1.1rem;
            line-height: 1.45;
        }
        .form-success i { flex-shrink: 0; margin-top: .1rem; }

        @media (prefers-color-scheme: dark) {
            .form-success { color: #6ee7b7; background: rgba(6,95,70,.15); border-color: rgba(6,95,70,.3); }
        }
        :root[data-theme="dark"] .form-success { color: #6ee7b7; background: rgba(6,95,70,.15); border-color: rgba(6,95,70,.3); }

        /* Fields */
        .field { margin-bottom: 1rem; }
        .field > label {
            display: block;
            font-size: .67rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--label-color);
            margin-bottom: .42rem;
        }

        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-icon {
            position: absolute;
            left: .8rem;
            color: var(--label-color);
            font-size: .8rem;
            pointer-events: none;
            opacity: .65;
        }

        .input-wrap input {
            flex: 1;
            font-size: max(.875rem, 16px);
            font-family: inherit;
            color: var(--form-text);
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 6px;
            padding: .68rem .8rem .68rem 2.15rem;
            outline: none;
            -webkit-appearance: none;
            transition: border-color .14s, box-shadow .14s;
            min-height: 44px;
        }
        .input-wrap input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .input-wrap input::placeholder { color: var(--form-muted); opacity: .45; }

        .btn-submit {
            display: block;
            width: 100%;
            padding: .85rem 1rem;
            min-height: 48px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: .9rem;
            font-family: inherit;
            font-weight: 600;
            letter-spacing: .04em;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: background .14s, box-shadow .14s, transform .08s;
        }
        .btn-submit:hover { background: var(--accent-dark); box-shadow: 0 4px 18px var(--accent-glow); }
        .btn-submit:active { transform: translateY(1px); }
        .btn-submit:disabled { opacity: .65; cursor: not-allowed; transform: none; }

        .auth-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .3rem;
            margin-top: 1.5rem;
            font-size: .78rem;
            color: var(--form-muted);
            text-decoration: none;
            transition: color .12s;
        }
        .auth-back:hover { color: var(--accent); }
        .auth-back i { font-size: .72rem; }

        .auth-security {
            margin-top: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            font-size: .7rem;
            color: var(--footer-color);
            letter-spacing: .02em;
        }
        .auth-security i { font-size: .72rem; }

        /* ── Responsive ── */
        @media (max-width: 820px) {
            .login-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 1.5rem 1.5rem 3.5rem; min-height: auto; }
            .brand-body { justify-content: flex-start; padding-top: 1.25rem; padding-bottom: 0; flex: none; }
            .brand-headline { font-size: clamp(1.3rem, 4vw, 1.65rem); margin-bottom: 0; }
            .brand-list { display: none; }
            .brand-eyebrow { margin-bottom: .75rem; }
            .ecg-wrap { height: 72px; }
            .auth-panel { align-items: flex-start; padding: 2rem 1.5rem 3rem; }
            .auth-box { max-width: 400px; }
        }

        @media (max-width: 640px) {
            .brand-panel { padding: 1.25rem 1.25rem 3rem; }
            .brand-eyebrow { display: none; }
            .brand-headline { font-size: 1.25rem; }
            .ecg-wrap { height: 60px; }
            .auth-panel { padding: 1.75rem 1.25rem 2.5rem; }
            .auth-box { max-width: 100%; }
            .auth-title { font-size: 1.3rem; }
        }

        @media (max-width: 400px) {
            .brand-panel { padding: 1rem 1rem 2.75rem; }
            .brand-wordmark { letter-spacing: .12em; }
            .brand-headline { font-size: 1.15rem; }
            .auth-panel { padding: 1.5rem 1rem 2rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .btn-submit, .input-wrap input, .auth-panel, .auth-back { transition: none; }
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
            <p class="brand-eyebrow">Telehealth Platform</p>
            <h1 class="brand-headline">Clinical precision.<br>Delivered at scale.</h1>
            <ul class="brand-list">
                <li>
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    HIPAA-compliant infrastructure with end-to-end encryption
                </li>
                <li>
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    Licensed clinicians with state-based smart assignment
                </li>
                <li>
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                    Automated intake, prescription, and case workflows
                </li>
            </ul>
        </div>

        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-box">

            <p class="auth-kicker">Account Recovery</p>
            <h2 class="auth-title">Forgot your password?</h2>
            <p class="auth-subtitle">Enter your email address and we'll send you a reset link.</p>

            @if(session('status'))
                <div class="form-success" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="form-alert" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            @unless(session('status'))
            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon" aria-hidden="true"></i>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="you@example.com"
                               autocomplete="email"
                               required
                               autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Send Reset Link
                </button>
            </form>
            @endunless

            <a href="{{ route('login') }}" class="auth-back">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Back to Sign In
            </a>

            <p class="auth-security">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                Encrypted connection &nbsp;&middot;&nbsp; HIPAA compliant
            </p>

        </div>
    </main>

</div>

<script>
(function () {
    'use strict';

    /* Disable button on submit to prevent double-send */
    var form = document.querySelector('form');
    var btn  = document.getElementById('submitBtn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Sending…';
        });
    }

    /* ── ECG heartbeat canvas ── */
    var canvas  = document.getElementById('ecgCanvas');
    var ctx     = canvas.getContext('2d');
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function resize() {
        canvas.width  = canvas.clientWidth;
        canvas.height = canvas.clientHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    var pts = [
        [0.00,  0.00], [0.05,  0.00],
        [0.09, -0.07], [0.13,  0.00], [0.24,  0.00],
        [0.27,  0.09], [0.32, -0.95], [0.36,  0.20],
        [0.40,  0.00], [0.46,  0.00],
        [0.51, -0.06], [0.58, -0.22], [0.65, -0.06],
        [0.71,  0.00], [1.00,  0.00]
    ];

    var CYCLE_W = 270; var AMP = 26; var BASE = 0.50; var SPEED = 0.55; var offset = 0;

    function draw() {
        var W = canvas.width; var H = canvas.height;
        ctx.clearRect(0, 0, W, H);
        var baseY = H * BASE;
        var grad = ctx.createLinearGradient(0, 0, W, 0);
        grad.addColorStop(0.00, 'rgba(0,180,216,0)');
        grad.addColorStop(0.07, 'rgba(0,180,216,0.58)');
        grad.addColorStop(0.93, 'rgba(0,180,216,0.58)');
        grad.addColorStop(1.00, 'rgba(0,180,216,0)');
        ctx.strokeStyle = grad; ctx.lineWidth = 1.8;
        ctx.shadowColor = 'rgba(0,180,216,0.42)'; ctx.shadowBlur = 10;
        ctx.lineJoin = 'round'; ctx.lineCap = 'round';
        var startX = -(offset % CYCLE_W);
        var numCycles = Math.ceil(W / CYCLE_W) + 2;
        ctx.beginPath(); var first = true;
        for (var c = -1; c < numCycles; c++) {
            for (var p = 0; p < pts.length; p++) {
                var x = startX + (c + pts[p][0]) * CYCLE_W;
                var y = baseY  +       pts[p][1]  * AMP;
                if (first) { ctx.moveTo(x, y); first = false; }
                else        { ctx.lineTo(x, y); }
            }
        }
        ctx.stroke();
        if (!reduced) { offset += SPEED; requestAnimationFrame(draw); }
    }
    draw();
}());
</script>

</body>
</html>
