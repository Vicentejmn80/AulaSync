<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') — AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --violet:#8b5cf6; --pink:#d946ef; --text:#1E1133; --muted:#6B4D87; --card:#fff; --bg:#FBFAF7; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,system-ui,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1100px; margin:0 auto; padding:28px 20px 60px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
        .brand { font-weight:900; font-size:20px; }
        .brand span { background:linear-gradient(90deg,#8b5cf6,#d946ef); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .nav { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .nav a, .nav button { border:0; background:transparent; color:var(--muted); font-weight:700; text-decoration:none; cursor:pointer; font-size:14px; }
        .nav a.active, .nav a:hover { color:var(--violet); }
        .card { background:var(--card); border:1px solid #eddcf7; border-radius:18px; padding:18px; box-shadow:0 12px 30px rgba(107,33,168,.06); margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; }
        .stat { padding:14px; border-radius:14px; background:#f8f1fc; }
        .stat b { display:block; font-size:26px; }
        .stat span { color:var(--muted); font-size:13px; font-weight:700; }
        .btn { display:inline-flex; align-items:center; gap:8px; border:0; border-radius:12px; padding:10px 14px; font-weight:800; cursor:pointer; text-decoration:none; color:#fff; background:linear-gradient(135deg,#8b5cf6,#d946ef); }
        .btn-ghost { background:#f3e8ff; color:#7c3aed; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #f0e6f7; vertical-align:middle; }
        select, input { border:1px solid #e8d7f3; border-radius:8px; padding:6px 8px; font:inherit; }
        .ok { color:#0F766E; font-weight:700; }
        .err { color:#B91C1C; font-weight:700; }
        h1 { margin:0 0 8px; font-size:28px; }
        p.sub { margin:0 0 18px; color:var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div class="brand">AulaSync <span>Super Admin</span></div>
            <nav class="nav">
                <a href="{{ url('/super-admin') }}" class="{{ request()->is('super-admin') ? 'active' : '' }}">Panel</a>
                <a href="{{ url('/super-admin/users') }}" class="{{ request()->is('super-admin/users') ? 'active' : '' }}">Usuarios</a>
                <form method="POST" action="{{ url('/logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit">Cerrar sesión</button>
                </form>
            </nav>
        </div>
        @if (session('success')) <p class="ok">{{ session('success') }}</p> @endif
        @if (session('error')) <p class="err">{{ session('error') }}</p> @endif
        @yield('content')
    </div>
</body>
</html>
