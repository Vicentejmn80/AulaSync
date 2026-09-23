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

        /* Glass card */
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

        .login-skeleton {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 40;
            background: #FBFAF7;
            padding: 24px 16px;
        }
        .login-skeleton.is-on { display: block; }
        .sk-bar {
            height: 14px; border-radius: 999px;
            background: linear-gradient(90deg, #f3e8ff 25%, #fce7f3 50%, #f3e8ff 75%);
            background-size: 200% 100%;
            animation: sk 1.1s ease-in-out infinite;
        }
        .sk-card {
            max-width: 420px; margin: 48px auto 0;
            background: #fff; border-radius: 1.5rem; padding: 20px;
            border: 1px solid #eeddf7;
        }
        .sk-row { height: 72px; border-radius: 1rem; margin-top: 12px; }
        @keyframes sk { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
        .as-loader {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 76px;
        }
        .as-orbit {
            position: relative;
            width: 72px;
            height: 72px;
            border-radius: 999px;
            border: 2px solid rgba(217, 70, 239, 0.22);
            animation: asPulse 1.8s ease-in-out infinite;
        }
        .as-orbit::before,
        .as-orbit::after {
            content: '';
            position: absolute;
            border-radius: 999px;
        }
        .as-orbit::before {
            inset: -2px;
            border-top: 3px solid #8b5cf6;
            border-right: 3px solid transparent;
            animation: asSpin 1.15s linear infinite;
        }
        .as-orbit::after {
            width: 18px;
            height: 18px;
            top: 27px;
            left: 27px;
            background: linear-gradient(135deg, #8b5cf6, #d946ef 55%, #f472b6);
            box-shadow: 0 8px 18px rgba(139, 92, 246, 0.28);
        }
        .as-loader-copy {
            margin-top: 10px;
            font-size: .86rem;
            color: #6B4D87;
            font-weight: 700;
            text-align: center;
        }
        .as-loader-sub {
            margin-top: 8px;
            font-size: .78rem;
            color: #7f5ea4;
            text-align: center;
            display: none;
        }
        .as-loader-sub.is-on {
            display: block;
        }
        .as-retry-btn {
            display: none;
            margin: 12px auto 0;
            border: 0;
            border-radius: 999px;
            padding: .56rem 1rem;
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #8b5cf6, #d946ef 55%, #f472b6);
            cursor: pointer;
        }
        .as-retry-btn.is-on {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }
        @keyframes asSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes asPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(217,70,239,.10); transform: scale(1); }
            50% { box-shadow: 0 0 0 10px rgba(217,70,239,.04); transform: scale(1.03); }
        }
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
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="glass-card">
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

    <div class="login-skeleton" id="login-skeleton" aria-live="polite" aria-busy="true">
        <div class="sk-card">
            <div class="sk-bar" style="width:46%;height:18px;"></div>
            <div class="sk-bar sk-row"></div>
            <div class="sk-bar sk-row"></div>
            <div class="as-loader" aria-hidden="true">
                <div class="as-orbit"></div>
            </div>
            <p class="as-loader-copy" id="login-loader-copy">Preparando tu espacio AulaSync…</p>
            <p class="as-loader-sub" id="login-loader-timeout-msg">Esto está tardando más de lo normal. Puedes reintentar.</p>
            <button type="button" class="as-retry-btn" id="login-loader-retry">
                <i class="fa-solid fa-rotate-right"></i> Reintentar
            </button>
        </div>
    </div>

    {{-- Limpia SW/cache en auth para evitar login con CSRF caducado (419) --}}
    <script>
        (function () {
            var form = document.getElementById('login-form');
            var skeleton = document.getElementById('login-skeleton');
            var submit = document.getElementById('login-submit');
            var timeoutMsg = document.getElementById('login-loader-timeout-msg');
            var retryBtn = document.getElementById('login-loader-retry');
            var timeoutHandle = null;
            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    window.location.reload();
                });
            }
            if (form) {
                form.addEventListener('submit', function () {
                    if (skeleton) skeleton.classList.add('is-on');
                    if (timeoutHandle) clearTimeout(timeoutHandle);
                    timeoutHandle = setTimeout(function () {
                        if (timeoutMsg) timeoutMsg.classList.add('is-on');
                        if (retryBtn) retryBtn.classList.add('is-on');
                    }, 9000);
                    if (submit) {
                        submit.disabled = true;
                        submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:.5rem;"></i>Entrando…';
                    }
                    try { sessionStorage.setItem('as.login.optimistic', '1'); } catch (e) {}
                });
            }
            if (!('serviceWorker' in navigator)) return;
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                regs.forEach(function (r) { r.unregister(); });
            });
            if ('caches' in window) {
                caches.keys().then(function (keys) {
                    keys.forEach(function (k) { caches.delete(k); });
                });
            }
        })();
    </script>
</body>
</html>
