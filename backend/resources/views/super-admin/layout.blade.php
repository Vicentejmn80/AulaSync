<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') — AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        body { margin:0; font-family:Inter,system-ui,sans-serif; background:var(--bg-primary); color:var(--text-primary); }
        .wrap { max-width:1180px; margin:0 auto; padding:24px 18px 70px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
        .brand { font-weight:900; font-size:20px; }
        .brand span { background:var(--nova-gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .nav { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .nav a, .nav button { border:0; background:transparent; color:var(--text-secondary); font-weight:700; text-decoration:none; cursor:pointer; font-size:13px; padding:6px 8px; border-radius:999px; }
        .nav a.active, .nav a:hover { color:var(--nova-violet); background:color-mix(in srgb, var(--nova-violet) 10%, transparent); }
        .card { background:var(--bg-card); border:1px solid var(--nova-glass-border); border-radius:18px; padding:18px; box-shadow:var(--nova-shadow); margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .stat { padding:14px; border-radius:14px; background:var(--bg-tertiary); }
        .stat b { display:block; font-size:24px; }
        .stat span { color:var(--text-secondary); font-size:12px; font-weight:700; }
        .btn { display:inline-flex; align-items:center; gap:8px; border:0; border-radius:12px; padding:9px 13px; font-weight:800; cursor:pointer; text-decoration:none; color:#fff; background:var(--nova-gradient); font-size:13px; }
        .btn-ghost { background:color-mix(in srgb, var(--nova-violet) 12%, transparent); color:var(--nova-violet); }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { text-align:left; padding:9px 7px; border-bottom:1px solid var(--nova-glass-border); vertical-align:middle; }
        select, input { border:1px solid var(--nova-glass-border); border-radius:8px; padding:6px 8px; font:inherit; background:var(--bg-secondary); color:var(--text-primary); }
        .ok { color:#0F766E; font-weight:700; }
        .err { color:#B91C1C; font-weight:700; }
        .muted { color:var(--text-secondary); }
        .empty { color:var(--text-secondary); padding:18px 4px; }
        .pill { display:inline-block; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:800; }
        .pill.activo { background:#dcfce7; color:#166534; }
        .pill.riesgo { background:#fef3c7; color:#92400e; }
        .pill.inactivo { background:#f1f5f9; color:#475569; }
        .filters { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
        .filters label { font-size:11px; font-weight:800; color:var(--text-secondary); display:block; margin-bottom:4px; }
        h1 { margin:0 0 6px; font-size:26px; }
        p.sub { margin:0 0 16px; color:var(--text-secondary); }
        .bars { display:flex; flex-direction:column; gap:8px; }
        .bar-row { display:grid; grid-template-columns:160px 1fr 48px; gap:8px; align-items:center; font-size:13px; }
        .bar { height:8px; border-radius:99px; background:var(--bg-tertiary); overflow:hidden; }
        .bar i { display:block; height:100%; background:var(--nova-gradient); }
        @media (max-width:720px) {
            .bar-row { grid-template-columns:1fr; }
            table { display:block; overflow-x:auto; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div class="brand">AulaSync <span>Founder Center</span></div>
            <nav class="nav">
                <a href="{{ url('/super-admin') }}" class="{{ ($section ?? '') === 'overview' ? 'active' : '' }}">Overview</a>
                <a href="{{ url('/super-admin/usage') }}" class="{{ ($section ?? '') === 'usage' ? 'active' : '' }}">Uso</a>
                <a href="{{ url('/super-admin/intelligence') }}" class="{{ ($section ?? '') === 'intelligence' ? 'active' : '' }}">IA</a>
                <a href="{{ url('/super-admin/schools') }}" class="{{ ($section ?? '') === 'schools' ? 'active' : '' }}">Colegios</a>
                <a href="{{ url('/super-admin/health') }}" class="{{ ($section ?? '') === 'health' ? 'active' : '' }}">Salud</a>
                <a href="{{ url('/super-admin/insights') }}" class="{{ ($section ?? '') === 'insights' ? 'active' : '' }}">Insights</a>
                <a href="{{ url('/super-admin/users') }}" class="{{ ($section ?? '') === 'users' ? 'active' : '' }}">Usuarios</a>
                <form method="POST" action="{{ url('/logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            </nav>
        </div>
        @isset($filters)
            <form class="card filters" method="GET">
                <div>
                    <label>Desde</label>
                    <input type="date" name="from" value="{{ $filters['from']->toDateString() }}">
                </div>
                <div>
                    <label>Hasta</label>
                    <input type="date" name="to" value="{{ $filters['to']->toDateString() }}">
                </div>
                <div>
                    <label>Colegio</label>
                    <select name="colegio_id">
                        <option value="">Todos</option>
                        @foreach (($filterOptions['colegios'] ?? []) as $colegio)
                            <option value="{{ $colegio->id }}" @selected(($filters['colegio_id'] ?? null) === $colegio->id)>{{ $colegio->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Rol</label>
                    <select name="role">
                        <option value="">Todos</option>
                        @foreach (['director' => 'Director', 'profesor' => 'Docente', 'representante' => 'Representante'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn" type="submit">Filtrar</button>
            </form>
        @endisset
        @if (session('success')) <p class="ok">{{ session('success') }}</p> @endif
        @if (session('error')) <p class="err">{{ session('error') }}</p> @endif
        @yield('content')
    </div>
</body>
</html>
