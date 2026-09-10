<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Identity — Doctor Portal</title>
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
            --panel-bg:      #091929;
            --panel-text:    #ffffff;
            --panel-body:    rgba(210,228,238,.60);
            --panel-rule:    rgba(210,228,238,.10);
            --accent:        #00B4D8;
            --accent-dark:   #0097B8;
            --accent-ring:   rgba(0,180,216,.22);
            --accent-glow:   rgba(0,180,216,.28);
            --form-bg:       #F5F8FB;
            --form-text:     #0D1B2A;
            --form-muted:    #566E7D;
            --input-bg:      #ffffff;
            --input-border:  #C3D2DB;
            --label-color:   #3B5365;
            --footer-color:  #8DAAB7;
            --success-bg:    rgba(4,120,87,.06);
            --success-border:rgba(4,120,87,.18);
            --success-text:  #065f46;
            --error-bg:      rgba(185,28,28,.06);
            --error-border:  rgba(185,28,28,.18);
            --error-text:    #b91c1c;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
                --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
                --success-bg:rgba(6,95,70,.15); --success-border:rgba(6,95,70,.30); --success-text:#6ee7b7;
                --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25); --error-text:#fca5a5;
            }
        }
        :root[data-theme="dark"] {
            --form-bg:#0B1D2D; --form-text:#D6E7EF; --form-muted:#6E8F9F;
            --input-bg:#0F2233; --input-border:#1B3549; --label-color:#7DA0B2; --footer-color:#3C5A6A;
            --success-bg:rgba(6,95,70,.15); --success-border:rgba(6,95,70,.30); --success-text:#6ee7b7;
            --error-bg:rgba(185,28,28,.12); --error-border:rgba(185,28,28,.25); --error-text:#fca5a5;
        }
        :root[data-theme="light"] {
            --form-bg:#F5F8FB; --form-text:#0D1B2A; --form-muted:#566E7D;
            --input-bg:#ffffff; --input-border:#C3D2DB; --label-color:#3B5365; --footer-color:#8DAAB7;
            --success-bg:rgba(4,120,87,.06); --success-border:rgba(4,120,87,.18); --success-text:#065f46;
            --error-bg:rgba(185,28,28,.06); --error-border:rgba(185,28,28,.18); --error-text:#b91c1c;
        }

        html { height: 100%; }
        body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: var(--form-bg);
        }

        .login-wrap { display: flex; min-height: 100vh; }

        /* ── Brand panel ── */
        .brand-panel {
            flex: 0 0 54%;
            background: var(--panel-bg);
            display: flex; flex-direction: column;
            padding: clamp(1.5rem,4vw,2.75rem) clamp(1.5rem,5vw,4rem);
            position: relative; overflow: hidden;
        }

        .brand-logo { display:flex;align-items:center;gap:.7rem;text-decoration:none;user-select:none;flex-shrink:0; }
        .brand-cross { position:relative;width:28px;height:28px;flex-shrink:0; }
        .brand-cross::before,.brand-cross::after { content:'';position:absolute;background:var(--accent);border-radius:2px; }
        .brand-cross::before { width:7px;height:100%;left:50%;transform:translateX(-50%); }
        .brand-cross::after  { width:100%;height:7px;top:50%;transform:translateY(-50%); }
        .brand-wordmark { font-size:clamp(.65rem,1.5vw,.72rem);font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#fff;line-height:1; }
        .brand-wordmark em { font-style:normal;color:var(--accent); }

        .brand-body { flex:1;display:flex;flex-direction:column;justify-content:center;padding-bottom:5.5rem; }

        .step-badge {
            display:inline-flex;align-items:center;gap:8px;
            background:rgba(0,180,216,.12);border:1px solid rgba(0,180,216,.22);
            border-radius:100px;padding:5px 14px 5px 10px;
            font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;
            color:var(--accent);margin-bottom:20px;
        }
        .step-dots { display:flex;gap:5px;align-items:center; }
        .step-dot { width:6px;height:6px;border-radius:50%;background:rgba(0,180,216,.3); }
        .step-dot.active { background:var(--accent);width:18px;border-radius:4px; }

        .brand-eyebrow { font-size:clamp(.6rem,1.2vw,.67rem);font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:var(--accent);opacity:.85;margin-bottom:clamp(.75rem,2vw,1.2rem); }

        .brand-headline {
            font-family: Georgia,'Times New Roman',serif;
            font-size:clamp(1.6rem,3vw,2.55rem);font-weight:400;line-height:1.3;
            color:var(--panel-text);text-wrap:balance;margin-bottom:clamp(1.25rem,2.5vw,2rem);
        }

        .shield-card {
            display:flex;align-items:flex-start;gap:14px;
            background:rgba(255,255,255,.04);border:1px solid rgba(210,228,238,.1);
            border-radius:12px;padding:18px 20px;margin-bottom:clamp(.75rem,1.5vw,1.25rem);
        }
        .shield-card-body { font-size:clamp(.72rem,1.3vw,.78rem);color:var(--panel-body);line-height:1.6; }
        .shield-card-body strong { color:#fff;font-weight:500;display:block;margin-bottom:2px; }

        .brand-list { list-style:none;border-top:1px solid var(--panel-rule); }
        .brand-list li { display:flex;align-items:flex-start;gap:.9rem;padding:clamp(.6rem,1.4vw,.85rem) 0;border-bottom:1px solid var(--panel-rule);color:var(--panel-body);font-size:clamp(.73rem,1.3vw,.78rem);line-height:1.55; }
        .brand-list li i { color:var(--accent);font-size:.88rem;opacity:.75;flex-shrink:0;margin-top:.15rem; }

        .ecg-wrap { position:absolute;bottom:0;left:0;right:0;height:88px;pointer-events:none; }
        #ecgCanvas { display:block;width:100%;height:100%; }

        /* ── Auth panel ── */
        .auth-panel {
            flex:1;background:var(--form-bg);
            display:flex;align-items:center;justify-content:center;
            padding:clamp(2rem,5vw,3rem) clamp(1.25rem,4vw,2rem);
        }
        .auth-box { width:100%;max-width:360px; }

        .auth-step { font-size:.67rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--form-muted);margin-bottom:.35rem; }
        .auth-title { font-size:clamp(1.35rem,3vw,1.55rem);font-weight:700;letter-spacing:-.025em;color:var(--form-text);margin-bottom:.45rem; }
        .auth-sub { font-size:.84rem;color:var(--form-muted);margin-bottom:1.6rem;line-height:1.6; }
        .auth-sub strong { color:var(--form-text);font-weight:600; }

        /* Alerts */
        .form-alert {
            display:flex;align-items:flex-start;gap:.5rem;padding:.7rem .9rem;
            background:var(--error-bg);border:1px solid var(--error-border);
            border-radius:8px;font-size:.81rem;color:var(--error-text);margin-bottom:1.1rem;line-height:1.45;
        }
        .form-alert i { flex-shrink:0;margin-top:.1rem; }
        .form-success {
            display:flex;align-items:flex-start;gap:.5rem;padding:.7rem .9rem;
            background:var(--success-bg);border:1px solid var(--success-border);
            border-radius:8px;font-size:.81rem;color:var(--success-text);margin-bottom:1.1rem;line-height:1.45;
        }
        .form-success i { flex-shrink:0;margin-top:.1rem; }

        /* Countdown */
        .countdown-wrap { margin-bottom:1.4rem; }
        .countdown-bar { height:3px;background:var(--input-border);border-radius:2px;overflow:hidden;margin-bottom:6px; }
        .countdown-fill { height:100%;width:100%;border-radius:2px;background:var(--accent);transition:width 1s linear,background-color .4s; }
        .countdown-meta { display:flex;justify-content:space-between;font-size:.72rem;color:var(--form-muted); }
        .countdown-meta span { font-variant-numeric:tabular-nums; }

        /* OTP grid — 6 individual cells */
        .otp-label { font-size:.67rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--label-color);margin-bottom:.65rem;display:block; }
        .otp-grid { display:flex;gap:10px;margin-bottom:1.25rem; }
        .otp-cell {
            flex:1;min-width:0;height:64px;
            font-size:1.65rem;font-weight:700;
            font-family:'Inter',monospace;
            text-align:center;
            color:var(--form-text);
            background:var(--input-bg);
            border:1.5px solid var(--input-border);
            border-radius:10px;
            outline:none;
            -webkit-appearance:none;appearance:none;
            transition:border-color .12s,box-shadow .12s,transform .1s,background-color .12s;
            cursor:text;caret-color:transparent;
            user-select:none;
        }
        .otp-cell:focus {
            border-color:var(--accent);
            box-shadow:0 0 0 3px var(--accent-ring);
            transform:translateY(-2px);
        }
        .otp-cell.filled {
            border-color:rgba(0,180,216,.45);
            background:rgba(0,180,216,.05);
        }
        .otp-cell.has-error {
            border-color:rgba(185,28,28,.45) !important;
            background:var(--error-bg) !important;
            box-shadow:none !important;
            transform:none !important;
            animation:otpShake .35s ease;
        }
        @keyframes otpShake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-5px)}
            60%{transform:translateX(5px)}
        }

        /* Buttons */
        .btn-submit {
            display:block;width:100%;padding:.9rem 1rem;min-height:50px;
            background:var(--accent);color:#fff;border:none;border-radius:8px;
            font-size:.9rem;font-family:inherit;font-weight:600;letter-spacing:.03em;
            cursor:pointer;transition:background .14s,box-shadow .14s,transform .08s;
            margin-bottom:1.1rem;
        }
        .btn-submit:hover:not(:disabled) { background:var(--accent-dark);box-shadow:0 4px 18px var(--accent-glow); }
        .btn-submit:active:not(:disabled) { transform:translateY(1px); }
        .btn-submit:disabled { opacity:.45;cursor:not-allowed; }

        .actions-row { display:flex;align-items:center;justify-content:space-between;gap:.5rem; }
        .btn-resend { background:none;border:none;font-size:.82rem;color:var(--accent);cursor:pointer;padding:0;font-family:inherit;font-weight:500; }
        .btn-resend:hover:not(:disabled) { text-decoration:underline; }
        .btn-resend:disabled { color:var(--form-muted);cursor:default; }

        .btn-back { display:flex;align-items:center;gap:.3rem;font-size:.82rem;color:var(--form-muted);text-decoration:none; }
        .btn-back:hover { color:var(--form-text); }

        .auth-security { margin-top:1.5rem;display:flex;align-items:center;justify-content:center;gap:.35rem;font-size:.7rem;color:var(--footer-color);letter-spacing:.02em; }

        /* Responsive */
        @media (max-width:820px) {
            .login-wrap { flex-direction:column; }
            .brand-panel { flex:none;padding:1.5rem 1.5rem 3.5rem;min-height:auto; }
            .brand-body { justify-content:flex-start;padding-top:1.25rem;padding-bottom:0;flex:none; }
            .brand-headline { font-size:clamp(1.3rem,4vw,1.65rem);margin-bottom:.75rem; }
            .brand-list,.shield-card { display:none; }
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
            .otp-cell { height:56px;font-size:1.4rem; }
            .otp-grid { gap:7px; }
        }
        @media (max-width:400px) {
            .otp-cell { height:50px;font-size:1.2rem;border-radius:8px; }
            .otp-grid { gap:5px; }
        }
        @media (prefers-reduced-motion:reduce) {
            .otp-cell,.btn-submit,.countdown-fill { transition:none;animation:none; }
        }
    </style>
</head>
<body>
<div class="login-wrap">

    <!-- Brand panel -->
    <aside class="brand-panel">
        <a class="brand-logo" href="/">
            <div class="brand-cross" aria-hidden="true"></div>
            <span class="brand-wordmark">Doctor <em>Portal</em></span>
        </a>

        <div class="brand-body">
            <div class="step-badge" aria-label="Step 2 of 2">
                <div class="step-dots" aria-hidden="true">
                    <span class="step-dot"></span>
                    <span class="step-dot active"></span>
                </div>
                Step 2 of 2 &nbsp;&ndash;&nbsp; Verify
            </div>

            <p class="brand-eyebrow">Two-Factor Auth</p>
            <h1 class="brand-headline">Identity<br>confirmed.</h1>

            <div class="shield-card">
                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;margin-top:1px;" aria-hidden="true">
                    <path d="M17 2.5L4.25 7.75V17C4.25 24.2825 9.98 31.1125 17 32.75C24.02 31.1125 29.75 24.2825 29.75 17V7.75L17 2.5Z" fill="rgba(0,180,216,.1)" stroke="#00B4D8" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M12.75 17L15.5 19.75L21.25 14" stroke="#00B4D8" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="shield-card-body">
                    <strong>Your account is protected.</strong>
                    Enter the 6-digit code from your email to complete sign-in.
                </div>
            </div>

            <ul class="brand-list">
                <li>
                    <i class="bi bi-envelope-check" aria-hidden="true"></i>
                    Code delivered to your registered email address
                </li>
                <li>
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    Expires in 10 minutes — resend if needed
                </li>
                <li>
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    Single-use, bcrypt-hashed, rate-limited
                </li>
            </ul>
        </div>

        <div class="ecg-wrap" aria-hidden="true">
            <canvas id="ecgCanvas"></canvas>
        </div>
    </aside>

    <!-- Auth panel -->
    <main class="auth-panel">
        <div class="auth-box">

            <p class="auth-step">Step 2 of 2</p>
            <h2 class="auth-title">Check your email</h2>
            <p class="auth-sub">
                We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>.
                Enter it below.
            </p>

            @if(session('status'))
            <div class="form-success" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                {{ session('status') }}
            </div>
            @endif

            @if($errors->any())
            <div class="form-alert" id="errorAlert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <!-- Countdown -->
            <div class="countdown-wrap" aria-label="Code expiry">
                <div class="countdown-bar" aria-hidden="true">
                    <div class="countdown-fill" id="countdownFill"></div>
                </div>
                <div class="countdown-meta">
                    <span>Code valid for</span>
                    <span id="countdownLabel" aria-live="polite" aria-atomic="true">10:00</span>
                </div>
            </div>

            <form method="POST" action="{{ route('mfa.verify') }}" id="mfaForm" novalidate>
                @csrf
                <input type="hidden" name="code" id="codeField">

                <label class="otp-label" for="otp-0">Verification code</label>
                <div class="otp-grid" id="otpGrid" role="group" aria-label="6-digit one-time code">
                    @for($i = 0; $i < 6; $i++)
                    <input class="otp-cell"
                           id="otp-{{ $i }}"
                           type="text"
                           inputmode="numeric"
                           pattern="[0-9]"
                           maxlength="1"
                           autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                           aria-label="Digit {{ $i + 1 }} of 6"
                           {{ $i === 0 ? 'autofocus' : '' }}>
                    @endfor
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    Verify &amp; Sign In
                </button>
            </form>

            <div class="actions-row">
                <form method="POST" action="{{ route('mfa.resend') }}" id="resendForm">
                    @csrf
                    <button type="submit" class="btn-resend" id="resendBtn">
                        Resend code
                    </button>
                </form>

                <a href="#" class="btn-back"
                   onclick="event.preventDefault();document.getElementById('logoutFrm').submit();">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Different account
                </a>
            </div>
            <form id="logoutFrm" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>

            <p class="auth-security">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                Encrypted &nbsp;&middot;&nbsp; HIPAA compliant &nbsp;&middot;&nbsp; Single-use code
            </p>

        </div>
    </main>

</div>

<script>
(function () {
    'use strict';

    /* ── OTP cells ── */
    var cells     = [].slice.call(document.querySelectorAll('.otp-cell'));
    var code      = document.getElementById('codeField');
    var submitBtn = document.getElementById('submitBtn');
    var form      = document.getElementById('mfaForm');
    var hasError  = !!document.getElementById('errorAlert');

    function syncCode() {
        var val = cells.map(function(c){ return c.value; }).join('');
        code.value    = val;
        var full      = /^\d{6}$/.test(val);
        submitBtn.disabled = !full;
        cells.forEach(function(c){ c.classList.toggle('filled', c.value !== ''); });
    }

    cells.forEach(function(cell, i) {
        cell.addEventListener('focus', function(){ cell.select(); });

        cell.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (cell.value === '' && i > 0) { e.preventDefault(); cells[i-1].value = ''; cells[i-1].focus(); }
                else { cell.value = ''; }
                syncCode();
            } else if (e.key === 'ArrowLeft'  && i > 0) { e.preventDefault(); cells[i-1].focus(); }
              else if (e.key === 'ArrowRight' && i < 5) { e.preventDefault(); cells[i+1].focus(); }
        });

        cell.addEventListener('input', function() {
            var v = cell.value.replace(/\D/g,'');
            if (v.length > 1) { spread(v, i); return; }
            cell.value = v;
            if (v && i < 5) cells[i+1].focus();
            syncCode();
        });

        cell.addEventListener('paste', function(e) {
            e.preventDefault();
            spread((e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,''), i);
        });
    });

    function spread(digits, start) {
        digits.split('').forEach(function(d, j) {
            if (start + j < 6) cells[start + j].value = d;
        });
        var next = Math.min(start + digits.length, 5);
        cells[next].focus();
        syncCode();
    }

    /* Shake cells on error from server */
    if (hasError) {
        cells.forEach(function(c){ c.classList.add('has-error'); c.value=''; });
        setTimeout(function(){
            cells.forEach(function(c){ c.classList.remove('has-error'); });
            cells[0].focus();
        }, 500);
    }

    form.addEventListener('submit', function(){
        cells.forEach(function(c){ c.disabled = true; });
        submitBtn.disabled   = true;
        submitBtn.textContent = 'Verifying…';
    });

    /* ── Countdown (10 min = 600 s) ── */
    var fill   = document.getElementById('countdownFill');
    var lbl    = document.getElementById('countdownLabel');
    var total  = 600;
    var rem    = total;

    var t = setInterval(function(){
        rem--;
        var pct = Math.max(0, rem / total * 100);
        fill.style.width = pct + '%';
        var m = Math.floor(rem/60), s = rem%60;
        lbl.textContent = m + ':' + (s<10?'0':'') + s;
        if (pct <= 30) fill.style.backgroundColor = '#f59e0b';
        if (pct <= 10) fill.style.backgroundColor = '#ef4444';
        if (rem <= 0) {
            clearInterval(t);
            lbl.textContent = 'Expired';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Code expired — resend to continue';
        }
    }, 1000);

    /* ── Resend cooldown ── */
    var resendBtn  = document.getElementById('resendBtn');
    var resendForm = document.getElementById('resendForm');
    var cooldown   = 30;

    resendForm.addEventListener('submit', function(e) {
        e.preventDefault();
        resendBtn.disabled = true;
        var left = cooldown;
        resendBtn.textContent = 'Resend in ' + left + 's';
        var cd = setInterval(function(){
            left--;
            resendBtn.textContent = left > 0 ? 'Resend in '+left+'s' : 'Resend code';
            if (left <= 0){ clearInterval(cd); resendBtn.disabled = false; }
        }, 1000);
        resendForm.submit();
    });

    /* ── ECG canvas ── */
    var canvas = document.getElementById('ecgCanvas');
    if (canvas) {
        var ctx     = canvas.getContext('2d');
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
