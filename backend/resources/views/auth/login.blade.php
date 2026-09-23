<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Iniciar Sesión · AulaSync</title>
    @include('partials.nav-prefetch', [
        'prefetchLogin' => false,
        'prefetchHub' => false,
        'idlePrefetch' => [],
    ])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { overflow-x: hidden; max-width: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FBFAF7;
            overflow-x: hidden;
            position: relative;
            color: #1E1133;
            padding: 16px;
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(500px 400px at 10% 12%, rgba(196, 85, 237, 0.18), transparent 68%),
                radial-gradient(460px 360px at 92% 84%, rgba(236, 72, 153, 0.14), transparent 70%),
                radial-gradient(400px 320px at 60% 18%, rgba(168, 85, 247, 0.10), transparent 70%);
        }

        /* Ambient glow orbs */
        .orb {
            position: fixed; border-radius: 50%; filter: blur(120px);
            pointer-events: none; z-index: 0;
        }
        .orb-1 {
            width: 500px; height: 500px; top: -120px; left: -100px;
            background: radial-gradient(circle, rgba(196,85,237,.22), transparent 70%);
        }
        .orb-2 {
            width: 420px; height: 420px; bottom: -100px; right: -100px;
            background: radial-gradient(circle, rgba(236,72,153,.18), transparent 70%);
        }
        .orb-3 {
            width: 300px; height: 300px; top: 50%; left: 60%;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, rgba(168,85,247,.12), transparent 70%);
        }

        /* Glass card — fade-out transition on submit */
        .glass-card {
            position: relative; z-index: 1;
            width: 100%; max-width: 420px;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(237, 221, 247, 0.95);
            border-radius: 1.75rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 28px 70px rgba(107, 33, 168, .10), 0 10px 24px rgba(236, 72, 153, .05);
            animation: cardIn .6s ease-out;
            transition: opacity .25s ease;
        }
        .glass-card.is-fading {
            opacity: 0;
            pointer-events: none;
        }
        @keyframes cardIn {
            from { opacity:0; transform:translateY(24px) scale(.97); }
            to   { opacity:1; transform:translateY(0)   scale(1);   }
        }

        /* Logo */
        .logo-row {
            display: flex; align-items: center; justify-content: center;
            gap: .75rem; margin-bottom: 1.75rem;
        }
        .logo-icon {
            width: 44px; height: 44px; border-radius: .875rem;
            background: linear-gradient(135deg, #8b5cf6, #d946ef, #f472b6);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 20px rgba(139,92,246,.22);
            overflow: hidden;
            padding: 6px;
        }
        .logo-icon img {
            width: 100%; height: 100%; object-fit: contain;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,.12));
        }
        .logo-text {
            font-size: 1.2rem; font-weight: 900; color: #1E1133;
        }
        .logo-text span {
            background: linear-gradient(90deg, #8b5cf6, #d946ef, #f472b6);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Headings */
        .card-title {
            font-size: 1.5rem; font-weight: 900; color: #1E1133;
            text-align: center; margin-bottom: .35rem;
        }
        .card-sub {
            text-align: center; color: #6B4D87;
            font-size: .88rem; margin-bottom: 1.75rem;
        }

        /* Inputs */
        .field { margin-bottom: 1.15rem; }
        .field label {
            display: block; font-size: .78rem; font-weight: 700;
            color: #7c3aed; margin-bottom: .4rem; letter-spacing: .02em;
        }
        .field input {
            width: 100%; padding: .75rem 1rem;
            min-height: 48px;
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(237, 221, 247, .95);
            border-radius: .875rem; color: #1E1133;
            font-size: 16px; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field input::placeholder { color: rgba(107,77,135,.45); }
        .field input:focus {
            border-color: #d946ef;
            box-shadow: 0 0 0 3px rgba(217,70,239,.16);
        }
        .field-error {
            display: block; font-size: .75rem; color: #f472b6;
            margin-top: .3rem;
        }

        /* Remember */
        .remember-row {
            display: flex; align-items: center; gap: .5rem;
            margin-bottom: 1.5rem;
        }
        .remember-row input[type=checkbox] {
            accent-color: #a855f7; width: 16px; height: 16px; cursor: pointer;
        }
        .remember-row label {
            font-size: .82rem; color: #6b4d87; cursor: pointer;
        }

        /* Submit */
        .btn-submit {
            width: 100%; padding: .85rem;
            min-height: 48px;
            background: linear-gradient(135deg, #8b5cf6, #d946ef 55%, #f472b6);
            color: #fff; font-weight: 800; font-size: .95rem;
            border: none; border-radius: .875rem; cursor: pointer;
            box-shadow: 0 10px 24px rgba(217,70,239,.22);
            transition: opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-submit:disabled { opacity: .72; cursor: wait; transform: none; }
        .btn-submit:hover {
            opacity: .92; transform: translateY(-2px);
            box-shadow: 0 12px 34px rgba(217,70,239,.28);
        }
        .btn-submit:active { transform: translateY(0); }

        /* Footer link */
        .card-footer-link {
            text-align: center; margin-top: 1.5rem;
            font-size: .85rem; color: #6b4d87;
        }
        .card-footer-link a {
            color: #c026d3; font-weight: 700; text-decoration: none;
            transition: color .15s;
        }
        .card-footer-link a:hover { color: #8b5cf6; text-decoration: underline; }

        /* Alert box */
        .alert-box {
            background: rgba(244,114,182,.08); border: 1px solid rgba(244,114,182,.18);
            border-radius: .75rem; padding: .65rem 1rem; margin-bottom: 1.25rem;
        }
        .alert-box li {
            font-size: .8rem; color: #be185d; margin-left: 1rem; list-style: disc;
        }

        /* ════════════════════════════════════════════════════
           SYNC NODES LOADER
           ════════════════════════════════════════════════════ */
        #sync-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 50;
            background: #FBFAF7;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .3s ease;
        }
        #sync-overlay.is-on    { display: flex; }
        #sync-overlay.is-vis   { opacity: 1; }

        /* SVG canvas */
        .sync-svg {
            width: min(200px, 60vw);
            height: min(200px, 60vw);
            overflow: visible;
        }

        /* Connecting lines */
        .sync-line {
            stroke: #8b5cf6;
            stroke-width: 1.5px;
            stroke-linecap: round;
            fill: none;
            animation: lineDraw 2.2s cubic-bezier(0.4, 0, 0.2, 1) infinite both;
        }
        @keyframes lineDraw {
            0%   { stroke-dashoffset: 80; opacity: 0; }
            12%  { opacity: .9; }
            42%  { stroke-dashoffset: 0;  opacity: 1; }
            58%  { stroke-dashoffset: 0;  opacity: 1; }
            88%  { opacity: .9; }
            100% { stroke-dashoffset: 80; opacity: 0; }
        }

        /* Role dots */
        .sync-dot {
            transform-box: fill-box;
            transform-origin: center;
            animation: dotPulse 2.2s ease-in-out infinite both;
        }
        @keyframes dotPulse {
            0%   { transform: scale(.45); opacity: .2; }
            42%  { transform: scale(1.2); opacity: 1; }
            58%  { transform: scale(1.1); opacity: .9; }
            100% { transform: scale(.45); opacity: .2; }
        }

        /* Central logo glow */
        .sync-logo-bg {
            animation: logoGlow 2.2s ease-in-out infinite;
        }
        @keyframes logoGlow {
            0%, 100% { filter: drop-shadow(0 0 5px rgba(139,92,246,.35)); }
            50%       { filter: drop-shadow(0 0 18px rgba(217,70,239,.80))
                                drop-shadow(0 0 32px rgba(244,114,182,.38)); }
        }

        /* Rotating status text */
        .sync-text-wrap {
            position: relative;
            height: 20px;
            margin-top: 22px;
            width: 230px;
            text-align: center;
        }
        .sync-msg {
            position: absolute;
            inset: 0;
            font-size: .86rem;
            font-weight: 700;
            color: #6B4D87;
            text-align: center;
            opacity: 0;
            transition: opacity .55s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        .sync-msg.is-on { opacity: 1; }

        /* Timeout panel — replaces animation */
        #sync-timeout {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            text-align: center;
            padding: 16px 24px;
        }
        #sync-timeout.is-on { display: flex; }
        #sync-timeout p {
            font-size: .88rem;
            font-weight: 600;
            color: #6B4D87;
            max-width: 240px;
            line-height: 1.5;
        }
        .sync-retry-btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            border: 0;
            border-radius: 999px;
            padding: .56rem 1.25rem;
            font-size: .82rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #8b5cf6, #d946ef 55%, #f472b6);
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(217,70,239,.22);
            transition: box-shadow .15s, transform .15s;
        }
        .sync-retry-btn:hover {
            box-shadow: 0 10px 28px rgba(217,70,239,.32);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    {{-- Login form card --}}
    <div class="glass-card" id="glass-card">
        <div class="logo-row">
            <div class="logo-icon"><img src="/images/emoji leyendo sin fondo.png" alt="" aria-hidden="true"></div>
            <div class="logo-text">AulaSync <span>Academia Inteligente</span></div>
        </div>

        <h1 class="card-title">Bienvenido de vuelta</h1>
        <p class="card-sub">Introduce tus credenciales para entrar a tu planificador.</p>

        @php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag())
        @if($errors->any())
        <div class="alert-box">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('login', absolute: false) }}" id="login-form">
            @csrf
            <div class="field">
                <label for="email">Correo Electrónico</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="tu@correo.com" required autofocus autocomplete="username" inputmode="email" enterkeyhint="next">
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" placeholder="Tu contraseña" required autocomplete="current-password" enterkeyhint="go">
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="remember-row">
                <input type="checkbox" name="remember" id="rememberMe">
                <label for="rememberMe">Recordarme</label>
            </div>
            <button type="submit" class="btn-submit" id="login-submit">
                <i class="fa-solid fa-right-to-bracket" style="margin-right:.5rem;"></i>Entrar Ahora
            </button>
        </form>

        <p class="card-footer-link">
            El acceso es por invitación tras una demo.
            <a href="{{ url('/#solicitar-demo') }}">Solicitar Demo</a>
        </p>
    </div>

    {{-- ── SYNC NODES LOADER ─────────────────────────────────────────── --}}
    <div id="sync-overlay" role="status" aria-label="Cargando tu espacio AulaSync">

        {{-- Animation section --}}
        <div id="sync-anim">
            <svg class="sync-svg" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    {{-- Brand gradient: violet → fuchsia → pink --}}
                    <linearGradient id="brandGrad" x1="0" y1="0" x2="200" y2="200" gradientUnits="userSpaceOnUse">
                        <stop offset="0%"   stop-color="#8b5cf6"/>
                        <stop offset="55%"  stop-color="#d946ef"/>
                        <stop offset="100%" stop-color="#f472b6"/>
                    </linearGradient>
                </defs>

                {{-- ── Connecting lines: center(100,100) → each role dot ── --}}
                {{-- Top (director) --}}
                <line class="sync-line"
                      x1="100" y1="100" x2="100" y2="20"
                      stroke-dasharray="80" stroke-dashoffset="80"/>
                {{-- Right (docente) --}}
                <line class="sync-line"
                      x1="100" y1="100" x2="180" y2="100"
                      stroke-dasharray="80" stroke-dashoffset="80"
                      style="animation-delay:.55s"/>
                {{-- Bottom (representante) --}}
                <line class="sync-line"
                      x1="100" y1="100" x2="100" y2="180"
                      stroke-dasharray="80" stroke-dashoffset="80"
                      style="animation-delay:1.1s"/>
                {{-- Left (alumno) --}}
                <line class="sync-line"
                      x1="100" y1="100" x2="20" y2="100"
                      stroke-dasharray="80" stroke-dashoffset="80"
                      style="animation-delay:1.65s"/>

                {{-- ── Role dots ── --}}
                <circle class="sync-dot" cx="100" cy="20"  r="7" fill="url(#brandGrad)" style="animation-delay:.33s"/>
                <circle class="sync-dot" cx="180" cy="100" r="7" fill="url(#brandGrad)" style="animation-delay:.88s"/>
                <circle class="sync-dot" cx="100" cy="180" r="7" fill="url(#brandGrad)" style="animation-delay:1.43s"/>
                <circle class="sync-dot" cx="20"  cy="100" r="7" fill="url(#brandGrad)" style="animation-delay:1.98s"/>

                {{-- ── Central logo ── --}}
                {{-- Gradient background rect --}}
                <rect class="sync-logo-bg"
                      x="78" y="78" width="44" height="44" rx="11"
                      fill="url(#brandGrad)"/>
                {{-- School emoji as image (same source as the card logo) --}}
                <image href="/images/emoji leyendo sin fondo.png"
                       x="84" y="84" width="32" height="32"
                       preserveAspectRatio="xMidYMid meet"/>
            </svg>

            {{-- Rotating status text --}}
            <div class="sync-text-wrap" aria-live="polite" aria-atomic="true">
                <span class="sync-msg is-on">Sincronizando tu colegio…</span>
                <span class="sync-msg">Cargando tus datos…</span>
                <span class="sync-msg">Casi listo…</span>
            </div>
        </div>

        {{-- Timeout panel — shown at 12 s, replaces animation --}}
        <div id="sync-timeout">
            <p>Esto está tardando más de lo normal. Puedes reintentar.</p>
            <button type="button" class="sync-retry-btn" id="sync-retry">
                <i class="fa-solid fa-rotate-right"></i> Reintentar
            </button>
        </div>
    </div>

    {{-- Limpia SW/cache en auth para evitar login con CSRF caducado (419) --}}
    <script>
        (function () {
            // ── Service-worker / cache cleanup ────────────────────────────────
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function (regs) {
                    regs.forEach(function (r) { r.unregister(); });
                });
            }
            if ('caches' in window) {
                caches.keys().then(function (keys) {
                    keys.forEach(function (k) { caches.delete(k); });
                });
            }

            // ── Element refs ──────────────────────────────────────────────────
            var form        = document.getElementById('login-form');
            var glassCard   = document.getElementById('glass-card');
            var syncOverlay = document.getElementById('sync-overlay');
            var syncAnim    = document.getElementById('sync-anim');
            var syncTimeout = document.getElementById('sync-timeout');
            var retryBtn    = document.getElementById('sync-retry');
            var submit      = document.getElementById('login-submit');

            if (!form) return;

            var controller    = null;   // AbortController for in-flight fetch
            var timeoutHandle = null;
            var msgInterval   = null;
            var TIMEOUT_MS    = 12000;

            // ── Text rotation ─────────────────────────────────────────────────
            function startTextRotation() {
                var msgs = syncOverlay.querySelectorAll('.sync-msg');
                var idx  = 0;
                // First message already has .is-on from HTML
                msgInterval = setInterval(function () {
                    msgs[idx].classList.remove('is-on');
                    idx = (idx + 1) % msgs.length;
                    msgs[idx].classList.add('is-on');
                }, 3500);
            }
            function stopTextRotation() {
                if (msgInterval) { clearInterval(msgInterval); msgInterval = null; }
            }

            // ── Show loader (fade card out, fade overlay in) ──────────────────
            function showLoader() {
                if (glassCard)   glassCard.classList.add('is-fading');
                if (syncOverlay) {
                    syncOverlay.classList.add('is-on');
                    // Next paint: trigger opacity transition
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            syncOverlay.classList.add('is-vis');
                        });
                    });
                }
                startTextRotation();
            }

            // ── Show timeout (replaces animation, no overlay change) ──────────
            function showTimeoutUI() {
                stopTextRotation();
                if (syncAnim)    syncAnim.style.display    = 'none';
                if (syncTimeout) syncTimeout.classList.add('is-on');
            }

            // ── Retry ─────────────────────────────────────────────────────────
            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    if (controller) { controller.abort(); controller = null; }
                    window.location.reload();
                });
            }

            // ── Intercept form submit with fetch + AbortController ────────────
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                if (submit) {
                    submit.disabled = true;
                    submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:.5rem;"></i>Entrando…';
                }
                showLoader();
                try { sessionStorage.setItem('as.login.optimistic', '1'); } catch (_) {}

                var body = new URLSearchParams(new FormData(form)).toString();
                controller = new AbortController();

                // 12 s hard safety timeout — aborts the fetch first
                if (timeoutHandle) clearTimeout(timeoutHandle);
                timeoutHandle = setTimeout(function () {
                    if (controller) { controller.abort(); controller = null; }
                    showTimeoutUI();
                }, TIMEOUT_MS);

                fetch(form.action, {
                    method:   'POST',
                    headers:  {
                        'Content-Type':     'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'text/html,application/xhtml+xml',
                    },
                    body:     body,
                    signal:   controller.signal,
                    redirect: 'manual',
                })
                .then(function (res) {
                    clearTimeout(timeoutHandle);
                    controller = null;

                    // 302 from Laravel → opaqueredirect (redirect: 'manual')
                    if (res.type === 'opaqueredirect' || res.redirected) {
                        window.location.href = res.url || '/teacher/hub';
                        return;
                    }

                    // 200 with validation errors → replace page
                    if (res.ok) {
                        res.text().then(function (html) {
                            document.open(); document.write(html); document.close();
                        });
                        return;
                    }

                    // 419 CSRF expired or other server error → reload for fresh token
                    window.location.reload();
                })
                .catch(function (err) {
                    clearTimeout(timeoutHandle);
                    controller = null;

                    if (err && err.name === 'AbortError') {
                        // Either 12 s timeout fired (UI already updated) or retry clicked
                        return;
                    }
                    // Network failure → reload
                    window.location.reload();
                });
            });
        })();
    </script>
</body>
</html>
