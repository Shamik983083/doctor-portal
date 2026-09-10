<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Identity — Doctor Portal</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"></noscript>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --panel-bg:     #091929;
            --accent:       #00B4D8;
            --accent-dark:  #0097B8;
            --accent-ring:  rgba(0,180,216,.22);
            --accent-glow:  rgba(0,180,216,.28);
            --form-bg:      #F5F8FB;
            --form-text:    #0D1B2A;
            --form-muted:   #566E7D;
            --input-bg:     #FFFFFF;
            --input-border: #C3D2DB;
            --label-color:  #3B5365;
            --footer-color: #8DAAB7;
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
            --form-bg: #0B1D2D; --form-text: #D6E7EF; --form-muted: #6E8F9F;
            --input-bg: #0F2233; --input-border: #1B3549; --label-color: #7DA0B2; --footer-color: #3C5A6A;
        }
        :root[data-theme="light"] {
            --form-bg: #F5F8FB; --form-text: #0D1B2A; --form-muted: #566E7D;
            --input-bg: #FFFFFF; --input-border: #C3D2DB; --label-color: #3B5365; --footer-color: #8DAAB7;
        }
        html { height: 100%; }
        body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .login-wrap { display: flex; min-height: 100vh; }

        /* Brand panel */
        .brand-panel {
            flex: 0 0 54%;
            background: var(--panel-bg);
            display: flex;
            flex-direction: column;
            padding: clamp(1.5rem,4vw,2.75rem) clamp(1.5rem,5vw,4rem);
            position: relative;
            overflow: hidden;
        }
        .brand-logo { display: flex; align-items: center; gap: .7rem; text-decoration: none; user-select: none; flex-shrink: 0; }
        .brand-cross { position: relative; width: 28px; height: 28px; flex-shrink: 0; }
        .brand-cross::before, .brand-cross::after { content: ''; position: absolute; background: var(--accent); border-radius: 2px; }
        .brand-cross::before { width: 7px; height: 100%; left: 50%; transform: translateX(-50%); }
        .brand-cross::after  { width: 100%; height: 7px; top: 50%; transform: translateY(-50%); }
        .brand-wordmark { font-size: clamp(.65rem,1.5vw,.72rem); font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: #fff; line-height: 1; }
        .brand-wordmark em { font-style: normal; color: var(--accent); }
        .brand-body { flex: 1; display: flex; flex-direction: column; justify-content: center; padding-bottom: 5.5rem; }
        .brand-eyebrow { font-size: clamp(.6rem,1.2vw,.67rem); font-weight: 600; letter-spacing: .22em; text-transform: uppercase; color: var(--accent); opacity: .85; margin-bottom: clamp(.75rem,2vw,1.2rem); }
        .brand-headline { font-family: Georgia,'Times New Roman',serif; font-size: clamp(1.6rem,3vw,2.55rem); font-weight: 400; line-height: 1.3; color: #fff; text-wrap: balance; }
        .ecg-wrap { position: absolute; bottom: 0; left: 0; right: 0; height: 88px; pointer-events: none; }
        #ecgCanvas { display: block; width: 100%; height: 100%; }

        /* Auth panel */
        .auth-panel { flex: 1; background: var(--form-bg); display: flex; align-items: center; justify-content: center; padding: clamp(2rem,5vw,3rem) clamp(1.25rem,4vw,2rem); }
        .auth-box { width: 100%; max-width: 330px; }
        .auth-kicker { font-size: clamp(.62rem,1.3vw,.67rem); font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--form-muted); margin-bottom: .38rem; }
        .auth-title { font-size: clamp(1.35rem,3vw,1.55rem); font-weight: 700; letter-spacing: -.025em; color: var(--form-text); margin-bottom: .5rem; }
        .auth-sub { font-size: .82rem; color: var(--form-muted); margin-bottom: 1.5rem; line-height: 1.5; }
        .auth-sub strong { color: var(--form-text); }

        .form-alert { display: flex; align-items: center; gap: .45rem; padding: .6rem .8rem; background: rgba(185,28,28,.07); border: 1px solid rgba(185,28,28,.18); border-radius: 6px; font-size: .8rem; color: #b91c1c; margin-bottom: 1.1rem; line-height: 1.4; }
        .form-alert i { flex-shrink: 0; }
        .form-success { display: flex; align-items: flex-start; gap: .45rem; padding: .7rem .85rem; background: rgba(4,120,87,.07); border: 1px solid rgba(4,120,87,.18); border-radius: 6px; font-size: .8rem; color: #065f46; margin-bottom: 1.1rem; line-height: 1.45; }
        .form-success i { flex-shrink: 0; margin-top: .1rem; }
        @media (prefers-color-scheme: dark) {
            .form-success { color: #6ee7b7; background: rgba(6,95,70,.15); border-color: rgba(6,95,70,.3); }
        }
        :root[data-theme="dark"] .form-success { color: #6ee7b7; background: rgba(6,95,70,.15); border-color: rgba(6,95,70,.3); }

        .field { margin-bottom: 1rem; }
        .field > label { display: block; font-size: .67rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--label-color); margin-bottom: .42rem; }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: .8rem; color: var(--label-color); font-size: .8rem; pointer-events: none; opacity: .65; }
        .input-wrap input {
            flex: 1;
            font-size: max(1.5rem, 24px);
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            letter-spacing: .35em;
            text-align: center;
            color: var(--form-text);
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 8px;
            padding: .8rem .8rem;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            transition: border-color .14s, box-shadow .14s;
            min-height: 56px;
        }
        .input-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
        .input-wrap input::placeholder { color: var(--form-muted); opacity: .35; letter-spacing: .15em; font-size: 1rem; }

        .btn-submit { display: block; width: 100%; padding: .85rem 1rem; min-height: 48px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: .9rem; font-family: inherit; font-weight: 600; letter-spacing: .04em; cursor: pointer; transition: background .14s, box-shadow .14s; margin-bottom: 1rem; }
        .btn-submit:hover { background: var(--accent-dark); box-shadow: 0 4px 18px var(--accent-glow); }

        .resend-row { text-align: center; margin-bottom: .75rem; }
        .btn-resend { background: none; border: none; font-size: .82rem; color: var(--accent); cursor: pointer; padding: 0; font-family: inherit; }
        .btn-resend:disabled { color: var(--form-muted); cursor: default; }
        .btn-resend:hover:not(:disabled) { text-decoration: underline; }

        .back-link { display: flex; align-items: center; gap: .3rem; font-size: .78rem; color: var(--form-muted); text-decoration: none; justify-content: center; }
        .back-link:hover { color: var(--form-text); }

        .auth-security { margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .35rem; font-size: .7rem; color: var(--footer-color); letter-spacing: .02em; }

        /* Expiry countdown */
        .expiry-bar { height: 3px; background: var(--input-border); border-radius: 2px; margin-bottom: 1.25rem; overflow: hidden; }
        .expiry-fill { height: 100%; background: var(--accent); border-radius: 2px; transition: width 1s linear, background .3s; width: 100%; }

        @media (max-width: 820px) {
            .login-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 1.5rem 1.5rem 3.5rem; min-height: auto; }
            .brand-body { justify-content: flex-start; padding-top: 1.25rem; padding-bottom: 0; flex: none; }
            .brand-headline { font-size: clamp(1.3rem,4vw,1.65rem); margin-bottom: 0; }
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
        }
        @media (prefers-reduced-motion: reduce) { .btn-submit, .input-wrap input, .auth-panel, .expiry-fill { transition: none; } }
    </style>
</head>
<body>
<div class="login-wrap">

    <aside class="brand-panel">
        <a class="brand-logo" href="/">
            <div class="brand-cross" aria-hidden="true"></div>
            <span class="brand-wordmark">Doctor <em>Portal</em></span>
        </a>
        <div class="brand-body">
            <p class="brand-eyebrow">Telehealth Platform</p>
            <h1 class="brand-headline">Two-step<br>verification.</h1>
        </div>
        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-box">

            <p class="auth-kicker">Security Check</p>
            <h2 class="auth-title">Enter your code</h2>
            <p class="auth-sub">
                We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>.
                It expires in 10 minutes.
            </p>

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

            {{-- Countdown bar: 10 min = 600 seconds --}}
            <div class="expiry-bar" aria-hidden="true">
                <div class="expiry-fill" id="expiryFill"></div>
            </div>

            <form method="POST" action="{{ route('mfa.verify') }}" id="mfaForm">
                @csrf

                <div class="field">
                    <label for="code">Verification code</label>
                    <div class="input-wrap">
                        <input type="text"
                               id="code"
                               name="code"
                               placeholder="000000"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               required
                               autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Verify &amp; Sign In</button>
            </form>

            <div class="resend-row">
                <form method="POST" action="{{ route('mfa.resend') }}" id="resendForm">
                    @csrf
                    <button type="submit" class="btn-resend" id="resendBtn">
                        Didn't receive a code? Resend
                    </button>
                </form>
            </div>

            <a href="{{ route('logout') }}" class="back-link"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Back to login
            </a>
            <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>

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

    /* ── Auto-submit on 6 digits ── */
    var codeInput = document.getElementById('code');
    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6) {
            document.getElementById('mfaForm').submit();
        }
    });

    /* ── Expiry countdown bar (600 s = 10 min) ── */
    var fill     = document.getElementById('expiryFill');
    var total    = 600;
    var elapsed  = 0;
    var timer    = setInterval(function () {
        elapsed++;
        var pct = Math.max(0, 100 - (elapsed / total * 100));
        fill.style.width = pct + '%';
        if (pct < 30) fill.style.background = '#f59e0b';
        if (pct < 10) fill.style.background = '#ef4444';
        if (elapsed >= total) clearInterval(timer);
    }, 1000);

    /* ── Resend throttle (30 s cooldown) ── */
    var resendBtn  = document.getElementById('resendBtn');
    var resendForm = document.getElementById('resendForm');
    var cooldown   = 30;
    resendForm.addEventListener('submit', function (e) {
        e.preventDefault();
        resendBtn.disabled = true;
        var left = cooldown;
        resendBtn.textContent = 'Resend in ' + left + 's';
        var cd = setInterval(function () {
            left--;
            resendBtn.textContent = 'Resend in ' + left + 's';
            if (left <= 0) {
                clearInterval(cd);
                resendBtn.disabled    = false;
                resendBtn.textContent = "Didn't receive a code? Resend";
            }
        }, 1000);
        // actually submit after updating UI
        resendForm.removeEventListener('submit', arguments.callee);
        resendForm.submit();
    });

    /* ── ECG canvas ── */
    var canvas = document.getElementById('ecgCanvas');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function resize() { canvas.width = canvas.clientWidth; canvas.height = canvas.clientHeight; }
        resize();
        window.addEventListener('resize', resize);
        var pts = [[0,.0],[.05,.0],[.09,-.07],[.13,.0],[.24,.0],[.27,.09],[.32,-.95],[.36,.20],[.40,.0],[.46,.0],[.51,-.06],[.58,-.22],[.65,-.06],[.71,.0],[1,.0]];
        var CYCLE_W=270, AMP=26, BASE=.50, SPEED=.55, offset=0;
        function draw() {
            var W=canvas.width, H=canvas.height;
            ctx.clearRect(0,0,W,H);
            var baseY=H*BASE;
            var grad=ctx.createLinearGradient(0,0,W,0);
            grad.addColorStop(0,'rgba(0,180,216,0)'); grad.addColorStop(.07,'rgba(0,180,216,.58)');
            grad.addColorStop(.93,'rgba(0,180,216,.58)'); grad.addColorStop(1,'rgba(0,180,216,0)');
            ctx.strokeStyle=grad; ctx.lineWidth=1.8; ctx.shadowColor='rgba(0,180,216,.42)'; ctx.shadowBlur=10;
            ctx.lineJoin='round'; ctx.lineCap='round';
            var startX=-(offset%CYCLE_W), numCycles=Math.ceil(W/CYCLE_W)+2;
            ctx.beginPath(); var first=true;
            for(var c=-1;c<numCycles;c++){for(var p=0;p<pts.length;p++){var x=startX+(c+pts[p][0])*CYCLE_W,y=baseY+pts[p][1]*AMP;if(first){ctx.moveTo(x,y);first=false;}else{ctx.lineTo(x,y);}}}
            ctx.stroke();
            if(!reduced){offset+=SPEED;requestAnimationFrame(draw);}
        }
        draw();
    }
}());
</script>
</body>
</html>
