<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta · Nova Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0c0118;
            overflow: hidden;
            position: relative;
        }

        .orb { position:fixed; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; }
        .orb-1 { width:500px; height:500px; top:-120px; right:-100px;
                  background:radial-gradient(circle,rgba(192,38,211,.45),transparent 70%); }
        .orb-2 { width:400px; height:400px; bottom:-80px; left:-80px;
                  background:radial-gradient(circle,rgba(124,58,237,.35),transparent 70%); }
        .orb-3 { width:280px; height:280px; top:40%; left:35%;
                  background:radial-gradient(circle,rgba(219,39,119,.18),transparent 70%); }

        .glass-card {
            position:relative; z-index:1;
            width:100%; max-width:440px;
            background:rgba(255,255,255,.06);
            backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
            border:1px solid rgba(255,255,255,.1);
            border-radius:1.75rem;
            padding:2.25rem 2rem;
            box-shadow:0 32px 64px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.08);
            animation:cardIn .6s ease-out;
        }
        @keyframes cardIn {
            from { opacity:0; transform:translateY(24px) scale(.97); }
            to   { opacity:1; transform:translateY(0)   scale(1);   }
        }

        .logo-row { display:flex; align-items:center; justify-content:center; gap:.75rem; margin-bottom:1.5rem; }
        .logo-icon {
            width:44px; height:44px; border-radius:.875rem;
            background:linear-gradient(135deg,#7c3aed,#c026d3);
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 20px rgba(124,58,237,.4);
        }
        .logo-icon i { color:#fff; font-size:1.15rem; }
        .logo-text { font-size:1.2rem; font-weight:900; color:#fff; }
        .logo-text span {
            background:linear-gradient(90deg,#e879f9,#f0abfc);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }

        .card-title { font-size:1.4rem; font-weight:900; color:#fff; text-align:center; margin-bottom:.3rem; }
        .card-sub { text-align:center; color:rgba(196,181,253,.7); font-size:.85rem; margin-bottom:1.5rem; }

        .field { margin-bottom:1rem; }
        .field label { display:block; font-size:.78rem; font-weight:700; color:#c4b5fd; margin-bottom:.35rem; letter-spacing:.02em; }
        .field input {
            width:100%; padding:.7rem 1rem;
            background:rgba(255,255,255,.07);
            border:1px solid rgba(255,255,255,.12);
            border-radius:.875rem; color:#fff; font-size:.9rem; outline:none;
            transition:border-color .2s, box-shadow .2s;
        }
        .field input::placeholder { color:rgba(196,181,253,.35); }
        .field input:focus { border-color:#a855f7; box-shadow:0 0 0 3px rgba(168,85,247,.2); }
        .field-error { display:block; font-size:.75rem; color:#f472b6; margin-top:.3rem; }

        .btn-submit {
            width:100%; padding:.8rem; margin-top:.5rem;
            background:linear-gradient(135deg,#7c3aed,#c026d3);
            color:#fff; font-weight:800; font-size:.95rem;
            border:none; border-radius:.875rem; cursor:pointer;
            box-shadow:0 6px 24px rgba(192,38,211,.35);
            transition:opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-submit:hover { opacity:.92; transform:translateY(-2px); box-shadow:0 8px 32px rgba(192,38,211,.5); }
        .btn-submit:active { transform:translateY(0); }

        .card-footer-link { text-align:center; margin-top:1.25rem; font-size:.85rem; color:rgba(196,181,253,.6); }
        .card-footer-link a { color:#e879f9; font-weight:700; text-decoration:none; transition:color .15s; }
        .card-footer-link a:hover { color:#f0abfc; text-decoration:underline; }

        .alert-box {
            background:rgba(244,114,182,.1); border:1px solid rgba(244,114,182,.25);
            border-radius:.75rem; padding:.6rem 1rem; margin-bottom:1.1rem;
        }
        .alert-box li { font-size:.8rem; color:#f9a8d4; margin-left:1rem; list-style:disc; }

        .row-2 { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="glass-card">
        <div class="logo-row">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div class="logo-text">Nova Academy <span>Academia Inteligente</span></div>
        </div>

        <h1 class="card-title">Crear tu cuenta</h1>
        <p class="card-sub">Únete a la nueva era de la planificación docente.</p>

        @if($errors->any())
        <div class="alert-box">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('register', absolute: false) }}">
            @csrf
            <div class="field">
                <label>Nombre Completo</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Tu nombre" required autofocus>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label>Correo Electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="tu@correo.com" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="row-2">
                <div class="field">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="Crea una clave" required>
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Confirmar</label>
                    <input type="password" name="password_confirmation" placeholder="Repite la clave" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-user-plus" style="margin-right:.5rem;"></i>Crear Cuenta
            </button>
        </form>

        <p class="card-footer-link">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}">Inicia sesión aquí</a>
        </p>
    </div>

    <script>
        (function () {
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
