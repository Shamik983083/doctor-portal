<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Your Password — Doctor Portal</title>
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
                --form-bg: #0B1D2D; --form-text: #D6E7EF; --form-muted: #6E8F9F;
                --input-bg: #0F2233; --input-border: #1B3549; --label-color: #7DA0B2; --footer-color: #3C5A6A;
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

        .brand-panel {
            flex: 0 0 54%;
            background: var(--panel-bg);
            display: flex; flex-direction: column;
            padding: clamp(1.5rem,4vw,2.75rem) clamp(1.5rem,5vw,4rem);
            position: relative; overflow: hidden;
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

        .auth-panel { flex: 1; background: var(--form-bg); display: flex; align-items: center; justify-content: center; padding: clamp(2rem,5vw,3rem) clamp(1.25rem,4vw,2rem); }
        .auth-box { width: 100%; max-width: 330px; }
        .auth-kicker { font-size: clamp(.62rem,1.3vw,.67rem); font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--form-muted); margin-bottom: .38rem; }
        .auth-title { font-size: clamp(1.35rem,3vw,1.55rem); font-weight: 700; letter-spacing: -.025em; color: var(--form-text); margin-bottom: .5rem; }
        .auth-sub { font-size: .82rem; color: var(--form-muted); margin-bottom: 1.5rem; line-height: 1.5; }

        .form-alert { display: flex; align-items: center; gap: .45rem; padding: .6rem .8rem; background: rgba(185,28,28,.07); border: 1px solid rgba(185,28,28,.18); border-radius: 6px; font-size: .8rem; color: #b91c1c; margin-bottom: 1.1rem; line-height: 1.4; }
        .form-alert i { flex-shrink: 0; }

        .field { margin-bottom: 1rem; }
        .field > label { display: block; font-size: .67rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--label-color); margin-bottom: .42rem; }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: .8rem; color: var(--label-color); font-size: .8rem; pointer-events: none; opacity: .65; }
        .input-wrap input {
            flex: 1; font-size: .92rem; color: var(--form-text); background: var(--input-bg);
            border: 1.5px solid var(--input-border); border-radius: 8px;
            padding: .72rem 2.5rem .72rem 2.35rem; outline: none;
            -webkit-appearance: none; appearance: none;
            transition: border-color .14s, box-shadow .14s;
        }
        .input-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
        .input-toggle { position: absolute; right: .75rem; background: none; border: none; padding: .2rem; cursor: pointer; color: var(--label-color); opacity: .5; }
        .input-toggle:hover { opacity: .85; }

        .strength-bar { height: 4px; background: var(--input-border); border-radius: 2px; margin-top: .45rem; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width .25s, background .25s; width: 0; }

        .btn-submit { display: block; width: 100%; padding: .85rem 1rem; min-height: 48px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: .9rem; font-family: inherit; font-weight: 600; letter-spacing: .04em; cursor: pointer; transition: background .14s, box-shadow .14s; }
        .btn-submit:hover { background: var(--accent-dark); box-shadow: 0 4px 18px var(--accent-glow); }
        .auth-security { margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .35rem; font-size: .7rem; color: var(--footer-color); letter-spacing: .02em; }

        @media (max-width: 820px) {
            .login-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 1.5rem 1.5rem 3.5rem; }
            .brand-body { justify-content: flex-start; padding-top: 1.25rem; padding-bottom: 0; flex: none; }
            .ecg-wrap { height: 72px; }
            .auth-panel { align-items: flex-start; }
            .auth-box { max-width: 400px; }
        }
        @media (prefers-reduced-motion: reduce) { .btn-submit, .input-wrap input, .strength-fill { transition: none; } }
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
            <p class="brand-eyebrow">Account Setup</p>
            <h1 class="brand-headline">Set your<br>password.</h1>
        </div>
        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-box">

            <p class="auth-kicker">Required Action</p>
            <h2 class="auth-title">Create a new password</h2>
            <p class="auth-sub">
                Your account requires a password change before you can continue.
                Choose a strong password you haven't used before.
            </p>

            @if($errors->any())
            <div class="form-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('password.force-reset.submit') }}">
                @csrf

                <div class="field">
                    <label for="password">New password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon" aria-hidden="true"></i>
                        <input type="password"
                               id="password"
                               name="password"
                               autocomplete="new-password"
                               required
                               autofocus>
                        <button type="button" class="input-toggle" id="togglePwd" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="strength-bar" aria-hidden="true">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock-fill input-icon" aria-hidden="true"></i>
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               autocomplete="new-password"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Set Password &amp; Continue</button>
            </form>

            <p class="auth-security">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                HIPAA compliant &nbsp;&middot;&nbsp; Encrypted connection
            </p>

        </div>
    </main>

</div>
<script>
(function () {
    'use strict';

    /* ── Show/hide password ── */
    var toggleBtn = document.getElementById('togglePwd');
    var pwdInput  = document.getElementById('password');
    var eyeIcon   = document.getElementById('eyeIcon');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var isText = pwdInput.type === 'text';
            pwdInput.type    = isText ? 'password' : 'text';
            eyeIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    /* ── Password strength bar ── */
    var fill = document.getElementById('strengthFill');
    var colors = ['#ef4444', '#f59e0b', '#22c55e', '#00B4D8'];
    pwdInput.addEventListener('input', function () {
        var v = this.value, score = 0;
        if (v.length >= 8)                    score++;
        if (/[A-Z]/.test(v))                  score++;
        if (/[0-9]/.test(v))                  score++;
        if (/[^A-Za-z0-9]/.test(v))           score++;
        fill.style.width      = (score / 4 * 100) + '%';
        fill.style.background = colors[Math.max(0, score - 1)] || colors[0];
    });

    /* ── ECG canvas ── */
    var canvas = document.getElementById('ecgCanvas');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function resize() { canvas.width = canvas.clientWidth; canvas.height = canvas.clientHeight; }
        resize(); window.addEventListener('resize', resize);
        var pts=[[0,.0],[.05,.0],[.09,-.07],[.13,.0],[.24,.0],[.27,.09],[.32,-.95],[.36,.20],[.40,.0],[.46,.0],[.51,-.06],[.58,-.22],[.65,-.06],[.71,.0],[1,.0]];
        var CYCLE_W=270,AMP=26,BASE=.50,SPEED=.55,offset=0;
        function draw(){var W=canvas.width,H=canvas.height;ctx.clearRect(0,0,W,H);var baseY=H*BASE;var grad=ctx.createLinearGradient(0,0,W,0);grad.addColorStop(0,'rgba(0,180,216,0)');grad.addColorStop(.07,'rgba(0,180,216,.58)');grad.addColorStop(.93,'rgba(0,180,216,.58)');grad.addColorStop(1,'rgba(0,180,216,0)');ctx.strokeStyle=grad;ctx.lineWidth=1.8;ctx.shadowColor='rgba(0,180,216,.42)';ctx.shadowBlur=10;ctx.lineJoin='round';ctx.lineCap='round';var startX=-(offset%CYCLE_W),numCycles=Math.ceil(W/CYCLE_W)+2;ctx.beginPath();var first=true;for(var c=-1;c<numCycles;c++){for(var p=0;p<pts.length;p++){var x=startX+(c+pts[p][0])*CYCLE_W,y=baseY+pts[p][1]*AMP;if(first){ctx.moveTo(x,y);first=false;}else{ctx.lineTo(x,y);}}}ctx.stroke();if(!reduced){offset+=SPEED;requestAnimationFrame(draw);}}
        draw();
    }
}());
</script>
</body>
</html>
