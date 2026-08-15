<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FBFAF7;
            overflow: hidden;
            position: relative;
            color: #1E1133;
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

        .orb { position:fixed; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; }
        .orb-1 { width:500px; height:500px; top:-120px; right:-100px;
                  background:radial-gradient(circle,rgba(196,85,237,.22),transparent 70%); }
        .orb-2 { width:400px; height:400px; bottom:-80px; left:-80px;
                  background:radial-gradient(circle,rgba(236,72,153,.18),transparent 70%); }
        .orb-3 { width:280px; height:280px; top:40%; left:35%;
                  background:radial-gradient(circle,rgba(168,85,247,.12),transparent 70%); }

        .glass-card {
            position:relative; z-index:1;
            width:100%; max-width:520px;
            background:rgba(255,255,255,.88);
            backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
            border:1px solid rgba(237,221,247,.95);
            border-radius:1.75rem;
            padding:2.25rem 2rem;
            box-shadow:0 28px 70px rgba(107,33,168,.10), 0 10px 24px rgba(236,72,153,.05);
            animation:cardIn .6s ease-out;
        }
        @keyframes cardIn {
            from { opacity:0; transform:translateY(24px) scale(.97); }
            to   { opacity:1; transform:translateY(0)   scale(1);   }
        }

        .logo-row { display:flex; align-items:center; justify-content:center; gap:.75rem; margin-bottom:1.5rem; }
        .logo-icon {
            width:44px; height:44px; border-radius:.875rem;
            background:linear-gradient(135deg,#8b5cf6,#d946ef,#f472b6);
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 10px 20px rgba(139,92,246,.22);
            overflow:hidden; padding:6px;
        }
        .logo-icon img { width:100%; height:100%; object-fit:contain; filter:drop-shadow(0 2px 5px rgba(0,0,0,.12)); }
        .logo-text { font-size:1.2rem; font-weight:900; color:#1E1133; }
        .logo-text span {
            background:linear-gradient(90deg,#8b5cf6,#d946ef,#f472b6);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }

        .card-title { font-size:1.4rem; font-weight:900; color:#1E1133; text-align:center; margin-bottom:.3rem; }
        .card-sub { text-align:center; color:#6B4D87; font-size:.85rem; margin-bottom:1.5rem; }

        .field { margin-bottom:1rem; }
        .field label { display:block; font-size:.78rem; font-weight:700; color:#7c3aed; margin-bottom:.35rem; letter-spacing:.02em; }
        .field input {
            width:100%; padding:.7rem 1rem;
            background:rgba(255,255,255,.94);
            border:1px solid rgba(237,221,247,.95);
            border-radius:.875rem; color:#1E1133; font-size:.9rem; outline:none;
            transition:border-color .2s, box-shadow .2s;
        }
        .field input::placeholder { color:rgba(107,77,135,.45); }
        .field input:focus { border-color:#d946ef; box-shadow:0 0 0 3px rgba(217,70,239,.16); }
        .field-error { display:block; font-size:.75rem; color:#be185d; margin-top:.3rem; }

        .btn-submit {
            width:100%; padding:.8rem; margin-top:.5rem;
            background:linear-gradient(135deg,#8b5cf6,#d946ef 55%,#f472b6);
            color:#fff; font-weight:800; font-size:.95rem;
            border:none; border-radius:.875rem; cursor:pointer;
            box-shadow:0 10px 24px rgba(217,70,239,.22);
            transition:opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-submit:hover { opacity:.92; transform:translateY(-2px); box-shadow:0 12px 34px rgba(217,70,239,.28); }
        .btn-submit:active { transform:translateY(0); }

        .card-footer-link { text-align:center; margin-top:1.25rem; font-size:.85rem; color:#6b4d87; }
        .card-footer-link a { color:#c026d3; font-weight:700; text-decoration:none; transition:color .15s; }
        .card-footer-link a:hover { color:#8b5cf6; text-decoration:underline; }

        .alert-box {
            background:rgba(244,114,182,.08); border:1px solid rgba(244,114,182,.18);
            border-radius:.75rem; padding:.6rem 1rem; margin-bottom:1.1rem;
        }
        .alert-box li { font-size:.8rem; color:#be185d; margin-left:1rem; list-style:disc; }

        .role-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.55rem; margin-bottom:1.1rem; }
        .role-pick {
            border:1px solid rgba(237,221,247,.95); background:rgba(255,255,255,.7);
            border-radius:1rem; padding:.7rem .4rem; text-align:center; cursor:pointer;
            font-size:.72rem; font-weight:800; color:#6B4D87; transition:.15s;
        }
        .role-pick i { display:block; font-size:1.05rem; margin-bottom:.35rem; color:#8b5cf6; }
        .role-pick input { display:none; }
        .role-pick.active, .role-pick:has(input:checked) {
            border-color:#d946ef; background:rgba(217,70,239,.08); color:#7c3aed;
            box-shadow:0 0 0 3px rgba(217,70,239,.12);
        }
        @media (max-width: 520px) { .role-grid { grid-template-columns:1fr; } }
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

        <h1 class="card-title">Crear tu cuenta</h1>
        <p class="card-sub">Elige tu rol. Luego te pediremos el código de tu colegio.</p>

        @php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag())
        @if($errors->any())
        <div class="alert-box">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('register', absolute: false) }}">
            @csrf
            <div class="role-grid">
                <label class="role-pick">
                    <input type="radio" name="role" value="profesor" {{ old('role') === 'profesor' ? 'checked' : '' }} required>
                    <i class="fa-solid fa-chalkboard-user"></i> Docente
                </label>
                <label class="role-pick">
                    <input type="radio" name="role" value="representante" {{ old('role') === 'representante' ? 'checked' : '' }}>
                    <i class="fa-solid fa-users"></i> Representante
                </label>
                <label class="role-pick">
                    <input type="radio" name="role" value="director" {{ old('role') === 'director' ? 'checked' : '' }}>
                    <i class="fa-solid fa-building-columns"></i> Director
                </label>
            </div>
            @error('role')<span class="field-error" style="display:block;margin-bottom:.8rem;">{{ $message }}</span>@enderror
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
            document.querySelectorAll('.role-pick').forEach(function (card) {
                card.addEventListener('click', function () {
                    document.querySelectorAll('.role-pick').forEach(function (el) { el.classList.remove('active'); });
                    card.classList.add('active');
                });
            });
            var checked = document.querySelector('.role-pick input:checked');
            if (checked) checked.closest('.role-pick').classList.add('active');
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
