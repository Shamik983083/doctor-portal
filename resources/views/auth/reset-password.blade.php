<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — Doctor Portal</title>
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
            --req-done:#059669; --req-done-bg:rgba(5,150,105,.08);
            --error-text:#b91c1c; --error-bg:rgba(185,28,28,.06); --error-border:rgba(185,28,28,.18);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
                --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
                --req-done:#34d399;
                --error-text:#fca5a5; --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25);
            }
        }
        :root[data-theme="dark"] {
            --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
            --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
            --req-done:#34d399;
            --error-text:#fca5a5; --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25);
        }
        :root[data-theme="light"] {
            --form-bg:#F5F8FB; --form-text:#0D1B2A; --form-muted:#566E7D;
            --input-bg:#ffffff; --input-border:#C3D2DB; --label-color:#3B5365; --footer-color:#8DAAB7;
            --req-done:#059669;
            --error-text:#b91c1c; --error-bg:rgba(185,28,28,.06); --error-border:rgba(185,28,28,.18);
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

        .tip-card {
            background:rgba(255,255,255,.04);border:1px solid rgba(210,228,238,.1);
            border-radius:12px;padding:18px 20px;
        }
        .tip-card-title { font-size:.7rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);opacity:.8;margin-bottom:10px; }
        .tip-card ul { list-style:none; }
        .tip-card li { display:flex;align-items:center;gap:8px;font-size:clamp(.72rem,1.3vw,.78rem);color:var(--panel-body);padding:4px 0;line-height:1.4; }
        .tip-card li i { color:var(--accent);opacity:.7;font-size:.8rem;flex-shrink:0; }

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

        .field { margin-bottom:1rem; }
        .field > label { display:block;font-size:.67rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--label-color);margin-bottom:.42rem; }

        .input-wrap { position:relative;display:flex;align-items:center; }
        .input-icon { position:absolute;left:.8rem;color:var(--label-color);font-size:.8rem;pointer-events:none;opacity:.65; }
        .input-wrap input {
            flex:1;font-size:max(.875rem,16px);font-family:inherit;
            color:var(--form-text);background:var(--input-bg);
            border:1.5px solid var(--input-border);border-radius:8px;
            padding:.72rem 2.75rem .72rem 2.35rem;
            outline:none;-webkit-appearance:none;appearance:none;
            transition:border-color .12s,box-shadow .12s;min-height:46px;
        }
        .input-wrap input:focus { border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-ring); }
        .input-wrap input:read-only { opacity:.6;cursor:default; }
        .input-wrap input.match   { border-color:var(--req-done); }
        .input-wrap input.mismatch { border-color:rgba(185,28,28,.4); }

        .btn-toggle { position:absolute;right:.7rem;background:none;border:none;cursor:pointer;color:var(--label-color);font-size:.85rem;padding:.25rem;opacity:.5;min-width:32px;min-height:32px;display:flex;align-items:center;justify-content:center;transition:opacity .12s; }
        .btn-toggle:hover { opacity:1; }

        .strength-wrap { margin-top:.45rem; }
        .strength-bar { height:4px;background:var(--input-border);border-radius:2px;overflow:hidden;margin-bottom:5px; }
        .strength-fill { height:100%;width:0;border-radius:2px;transition:width .25s,background-color .25s; }
        .strength-label { font-size:.7rem;color:var(--form-muted);font-weight:500; }

        .req-list { list-style:none;margin-top:.75rem;margin-bottom:1rem;display:grid;grid-template-columns:1fr 1fr;gap:4px 8px; }
        .req-item { display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--form-muted);transition:color .15s; }
        .req-item.done { color:var(--req-done); }
        .req-item i { font-size:.75rem;flex-shrink:0;transition:color .15s; }
        .req-item.done i { color:var(--req-done); }

        .field-hint { font-size:.7rem;color:var(--form-muted);margin-top:.35rem; }

        .btn-submit {
            display:block;width:100%;padding:.9rem 1rem;min-height:50px;
            background:var(--accent);color:#fff;border:none;border-radius:8px;
            font-size:.9rem;font-family:inherit;font-weight:600;letter-spacing:.03em;
            cursor:pointer;transition:background .14s,box-shadow .14s,transform .08s;
            margin-top:1.25rem;
        }
        .btn-submit:hover:not(:disabled) { background:var(--accent-dark);box-shadow:0 4px 18px var(--accent-glow); }
        .btn-submit:active:not(:disabled) { transform:translateY(1px); }
        .btn-submit:disabled { opacity:.45;cursor:not-allowed; }

        .auth-back { display:flex;align-items:center;justify-content:center;gap:.3rem;margin-top:1.25rem;font-size:.78rem;color:var(--form-muted);text-decoration:none;transition:color .12s; }
        .auth-back:hover { color:var(--accent); }

        .auth-security { margin-top:1.5rem;display:flex;align-items:center;justify-content:center;gap:.35rem;font-size:.7rem;color:var(--footer-color);letter-spacing:.02em; }

        @media (max-width:820px) {
            .login-wrap { flex-direction:column; }
            .brand-panel { flex:none;padding:1.5rem 1.5rem 3.5rem;min-height:auto; }
            .brand-body { justify-content:flex-start;padding-top:1.25rem;padding-bottom:0;flex:none; }
            .brand-headline { font-size:clamp(1.3rem,4vw,1.65rem);margin-bottom:.75rem; }
            .tip-card { display:none; }
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
            .req-list { grid-template-columns:1fr; }
        }
        @media (prefers-reduced-motion:reduce) {
            .btn-submit,.input-wrap input,.strength-fill,.req-item,.req-item i { transition:none; }
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
            <h1 class="brand-headline">Set a strong<br>new password.</h1>

            <div class="tip-card">
                <p class="tip-card-title">Strong password tips</p>
                <ul>
                    <li><i class="bi bi-lightbulb" aria-hidden="true"></i>Use a passphrase — 3 or more random words</li>
                    <li><i class="bi bi-lightbulb" aria-hidden="true"></i>Mix uppercase, numbers and symbols</li>
                    <li><i class="bi bi-lightbulb" aria-hidden="true"></i>Never reuse passwords across sites</li>
                    <li><i class="bi bi-lightbulb" aria-hidden="true"></i>Consider a password manager</li>
                </ul>
            </div>
        </div>
        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-box">

            <p class="auth-kicker">Account Recovery</p>
            <h2 class="auth-title">Set new password</h2>
            <p class="auth-sub">Choose a strong password to secure your account.</p>

            @if($errors->any())
            <div class="form-alert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" id="resetForm" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon" aria-hidden="true"></i>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $email) }}"
                               autocomplete="email" readonly aria-readonly="true">
                    </div>
                </div>

                <div class="field">
                    <label for="password">New password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon" aria-hidden="true"></i>
                        <input type="password" id="password" name="password"
                               placeholder="••••••••" autocomplete="new-password"
                               required autofocus minlength="8" aria-describedby="pwdStrengthLabel">
                        <button type="button" class="btn-toggle" id="togglePwd" aria-label="Show password">
                            <i class="bi bi-eye" id="eyeIcon1" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar" aria-hidden="true">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span class="strength-label" id="pwdStrengthLabel" aria-live="polite"></span>
                    </div>
                    <ul class="req-list" aria-label="Password requirements">
                        <li class="req-item" id="req-len"    ><i class="bi bi-circle" aria-hidden="true"></i>8+ characters</li>
                        <li class="req-item" id="req-upper"  ><i class="bi bi-circle" aria-hidden="true"></i>Uppercase letter</li>
                        <li class="req-item" id="req-lower"  ><i class="bi bi-circle" aria-hidden="true"></i>Lowercase letter</li>
                        <li class="req-item" id="req-num"    ><i class="bi bi-circle" aria-hidden="true"></i>Number</li>
                        <li class="req-item" id="req-special"><i class="bi bi-circle" aria-hidden="true"></i>Special character</li>
                    </ul>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon" aria-hidden="true"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               placeholder="••••••••" autocomplete="new-password"
                               required aria-describedby="matchHint">
                        <button type="button" class="btn-toggle" id="toggleConf" aria-label="Show confirm password">
                            <i class="bi bi-eye" id="eyeIcon2" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="field-hint" id="matchHint" aria-live="polite"></p>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Reset Password
                </button>
            </form>

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

    var pwd    = document.getElementById('password');
    var conf   = document.getElementById('password_confirmation');
    var fill   = document.getElementById('strengthFill');
    var slbl   = document.getElementById('pwdStrengthLabel');
    var hint   = document.getElementById('matchHint');
    var form   = document.getElementById('resetForm');
    var submit = document.getElementById('submitBtn');

    var STRENGTHS = [
        { label:'Too short', color:'#ef4444',  pct:'15%'  },
        { label:'Weak',      color:'#f97316',  pct:'30%'  },
        { label:'Fair',      color:'#f59e0b',  pct:'55%'  },
        { label:'Good',      color:'#84cc16',  pct:'75%'  },
        { label:'Strong',    color:'#22c55e',  pct:'100%' }
    ];

    var REQS = [
        { id:'req-len',     test:function(v){ return v.length >= 8; } },
        { id:'req-upper',   test:function(v){ return /[A-Z]/.test(v); } },
        { id:'req-lower',   test:function(v){ return /[a-z]/.test(v); } },
        { id:'req-num',     test:function(v){ return /[0-9]/.test(v); } },
        { id:'req-special', test:function(v){ return /[^A-Za-z0-9]/.test(v); } }
    ];

    function score(v) {
        if (!v) return -1;
        var s = 0;
        if (v.length >= 8)                        s++;
        if (v.length >= 12)                        s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v))   s++;
        if (/[0-9]/.test(v))                       s++;
        if (/[^A-Za-z0-9]/.test(v))               s++;
        return Math.min(s, 4);
    }

    pwd.addEventListener('input', function () {
        var v = pwd.value;
        var sc = score(v);
        if (v) {
            var st = STRENGTHS[sc];
            fill.style.width = st.pct; fill.style.backgroundColor = st.color;
            slbl.textContent = st.label; slbl.style.color = st.color;
        } else {
            fill.style.width = '0'; slbl.textContent = '';
        }
        REQS.forEach(function(r) {
            var el = document.getElementById(r.id);
            var ico = el.querySelector('i');
            if (r.test(v)) { el.classList.add('done'); ico.className = 'bi bi-check-circle-fill'; }
            else            { el.classList.remove('done'); ico.className = 'bi bi-circle'; }
        });
        checkMatch();
    });

    conf.addEventListener('input', checkMatch);

    function checkMatch() {
        if (!conf.value) { hint.textContent = ''; conf.className = ''; return; }
        if (pwd.value === conf.value) {
            hint.textContent = '✓ Passwords match'; hint.style.color = 'var(--req-done)';
            conf.classList.remove('mismatch'); conf.classList.add('match');
        } else {
            hint.textContent = 'Passwords do not match'; hint.style.color = 'var(--error-text)';
            conf.classList.remove('match'); conf.classList.add('mismatch');
        }
    }

    function makeToggle(btnId, inputEl, iconId) {
        document.getElementById(btnId).addEventListener('click', function() {
            var show = inputEl.type === 'password';
            inputEl.type = show ? 'text' : 'password';
            document.getElementById(iconId).className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }
    makeToggle('togglePwd',  pwd,  'eyeIcon1');
    makeToggle('toggleConf', conf, 'eyeIcon2');

    form.addEventListener('submit', function(e) {
        if (pwd.value !== conf.value) { e.preventDefault(); conf.focus(); checkMatch(); return; }
        submit.disabled = true; submit.textContent = 'Resetting…';
    });

    conf.addEventListener('input', function(){ conf.setCustomValidity(''); });

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
