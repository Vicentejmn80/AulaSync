<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Super Administrador - AulaSync</title>
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
            width:100%; max-width:600px;
            background:rgba(255,255,255,.88);
            backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
            border:1px solid rgba(237,221,247,.95);
            border-radius:1.75rem;
            padding:2.25rem 2rem;
            box-shadow:0 28px 70px rgba(107,33,168,.10), 0 10px 24px rgba(236,72,153,.05);
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

        .btn-super-admin {
            width:100%; padding:.8rem;
            background:linear-gradient(135deg,#8b5cf6,#d946ef 55%,#f472b6);
            color:#fff; font-weight:800; font-size:.95rem;
            border:none; border-radius:.875rem; cursor:pointer;
            box-shadow:0 10px 24px rgba(217,70,239,.22);
            transition:opacity .15s, transform .15s, box-shadow .15s;
        }
        .btn-super-admin:hover { opacity:.92; transform:translateY(-2px); box-shadow:0 12px 34px rgba(217,70,239,.28); }
        .btn-super-admin:active { transform:translateY(0); }

        .card-footer-link { text-align:center; margin-top:1.25rem; font-size:.85rem; color:#6b4d87; }
        .card-footer-link a { color:#c026d3; font-weight:700; text-decoration:none; transition:color .15s; }
        .card-footer-link a:hover { color:#8b5cf6; text-decoration:underline; }
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

        <h1 class="card-title">Panel de Super Administrador</h1>
        <p class="card-sub">Bienvenido, Vicente. Acceso total al sistema.</p>

        <div style="text-align: center;">
            <button class="btn-super-admin">
                <i class="fa-solid fa-user-plus" style="margin-right:.5rem;"></i>Gestión de Usuarios
            </button>
        </div>

        <p class="card-footer-link">
            <a href="{{ route('login') }}">Cerrar sesión</a>
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