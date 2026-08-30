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
        :root {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-500: #64748b;
            --slate-700: #334155;
            --slate-900: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
            color: var(--slate-900);
            background:
                radial-gradient(circle at 10% -20%, rgba(79, 70, 229, 0.12), transparent 42%),
                radial-gradient(circle at 110% 10%, rgba(6, 182, 212, 0.12), transparent 36%),
                linear-gradient(180deg, #f8fafc 0%, #f3f4f6 100%);
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .22;
            background-image:
                linear-gradient(rgba(148, 163, 184, .10) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, .10) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .wrap {
            position: relative;
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 20px 64px;
        }
        .top {
            position: sticky;
            top: 14px;
            z-index: 20;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, .25);
            background: rgba(255, 255, 255, .78);
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 28px rgba(15, 23, 42, .08);
        }
        .brand { font-weight: 900; font-size: 20px; letter-spacing: -.02em; }
        .brand span {
            background: linear-gradient(120deg, #4f46e5, #9333ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .nav a, .nav button {
            border: 1px solid transparent;
            background: transparent;
            color: var(--slate-500);
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            line-height: 1;
            padding: 8px 11px;
            border-radius: 999px;
            transition: all .22s ease;
        }
        .nav a.active {
            color: #312e81;
            border-color: rgba(99, 102, 241, .30);
            background: linear-gradient(135deg, rgba(99, 102, 241, .16), rgba(139, 92, 246, .14));
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .45);
        }
        .nav a:hover, .nav button:hover {
            color: var(--slate-900);
            border-color: rgba(148, 163, 184, .35);
            background: rgba(255, 255, 255, .86);
        }
        .card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .30);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
            margin-bottom: 16px;
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .card:hover {
            box-shadow: 0 14px 34px rgba(15, 23, 42, .11);
            transform: translateY(-1px);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat {
            position: relative;
            overflow: hidden;
            padding: 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .26);
            box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
            transition: all .2s ease;
        }
        .stat:hover {
            border-color: rgba(99, 102, 241, .28);
            box-shadow: 0 10px 24px rgba(79, 70, 229, .13);
            transform: translateY(-1px);
        }
        .stat::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #9333ea);
        }
        .stat b {
            display: block;
            font-size: 25px;
            line-height: 1.1;
            letter-spacing: -.02em;
            margin: 6px 0 3px;
        }
        .stat span {
            color: var(--slate-500);
            font-size: 12px;
            font-weight: 700;
        }
        .stat-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .metric-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }
        .metric-icon.cyan {
            background: #ecfeff;
            color: #0e7490;
            border-color: #a5f3fc;
        }
        .metric-icon.emerald {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .trend-badge {
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .trend-badge.up { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .trend-badge.warn { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .trend-badge.neutral { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 0;
            border-radius: 12px;
            padding: 9px 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            font-size: 13px;
            transition: all .2s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(79, 70, 229, .32); }
        .btn-ghost {
            background: #f8fafc;
            color: #312e81;
            border: 1px solid #cbd5e1;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            padding: 7px 11px;
            font-size: 12px;
        }
        [x-cloak] { display: none !important; }
        .sa-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 18px;
            backdrop-filter: blur(5px);
        }
        .sa-dialog {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .30);
            border-radius: 16px;
            padding: 22px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(15, 23, 42, .20);
        }
        .sa-dialog h3 { margin: 0 0 8px; font-size: 18px; }
        .sa-dialog p { margin: 0 0 16px; color: var(--slate-500); }
        .sa-actions { display: flex; gap: 8px; justify-content: flex-end; }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        thead th {
            text-align: left;
            padding: 11px 9px;
            color: #475569;
            font-weight: 700;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .28);
            white-space: nowrap;
        }
        tbody td {
            text-align: left;
            padding: 11px 9px;
            border-bottom: 1px solid rgba(148, 163, 184, .20);
            vertical-align: middle;
            color: #0f172a;
        }
        tbody tr:nth-child(even) td { background: rgba(248, 250, 252, .46); }
        tbody tr:hover td { background: rgba(224, 231, 255, .36); }
        .table-avatar {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: #1e1b4b;
            background: linear-gradient(135deg, #c7d2fe, #bfdbfe);
            margin-right: 8px;
            flex: 0 0 auto;
        }
        .table-identity {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .status-badge.success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .status-badge.failed { background: #fff1f2; color: #be123c; border-color: #fda4af; }
        .status-badge.warn { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .status-badge.neutral { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
        select, input {
            border: 1px solid rgba(148, 163, 184, .44);
            border-radius: 10px;
            padding: 8px 10px;
            font: inherit;
            background: #fff;
            color: #0f172a;
            transition: all .2s ease;
        }
        select:focus, input:focus {
            outline: none;
            border-color: rgba(99, 102, 241, .76);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, .14);
        }
        .ok, .err {
            margin: 0 0 14px;
            padding: 11px 13px;
            border-radius: 12px;
            font-weight: 700;
            border: 1px solid transparent;
            background: #fff;
            width: fit-content;
        }
        .ok { color: #065f46; border-color: #a7f3d0; background: #ecfdf5; }
        .err { color: #991b1b; border-color: #fca5a5; background: #fef2f2; }
        .muted { color: var(--slate-500); }
        .empty {
            color: var(--slate-500);
            padding: 16px 8px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            margin: 0;
        }
        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .pill.activo { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .pill.riesgo { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .pill.inactivo { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(10px);
        }
        .filters label {
            font-size: 11px;
            font-weight: 700;
            color: var(--slate-500);
            display: block;
            margin-bottom: 5px;
        }
        h1 { margin: 0 0 6px; font-size: 28px; letter-spacing: -.02em; }
        h3 { margin-top: 0; letter-spacing: -.01em; }
        p.sub { margin: 0 0 16px; color: var(--slate-500); max-width: 960px; }
        .bars { display: flex; flex-direction: column; gap: 10px; }
        .bar-row {
            display: grid;
            grid-template-columns: 220px minmax(220px, 1fr) 52px;
            gap: 10px;
            align-items: center;
            font-size: 13px;
        }
        .bar {
            height: 10px;
            border-radius: 999px;
            background: #eef2ff;
            overflow: hidden;
            border: 1px solid #dbeafe;
        }
        .bar i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 50%, #06b6d4 100%);
            transition: width .5s ease;
        }
        .school-stack { display: flex; flex-direction: column; gap: 12px; }
        .school-card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .30);
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
            overflow: hidden;
            transition: box-shadow .2s ease, border-color .2s ease;
        }
        .school-card.is-open {
            border-color: rgba(99, 102, 241, .38);
            box-shadow: 0 16px 36px rgba(79, 70, 229, .12);
        }
        .school-card-head {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(180px, 1.4fr) repeat(4, minmax(90px, 1fr)) 28px;
            gap: 12px;
            align-items: center;
            padding: 16px 18px;
            background: transparent;
            border: 0;
            cursor: pointer;
            text-align: left;
            color: inherit;
            font: inherit;
        }
        .school-card-head:hover { background: rgba(238, 242, 255, .45); }
        .school-ident { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .school-ident strong { display: block; font-size: 16px; letter-spacing: -.02em; }
        .school-ident small { display: block; color: var(--slate-500); font-size: 12px; font-weight: 600; }
        .school-pulse { display: flex; flex-direction: column; gap: 2px; }
        .school-pulse b { font-size: 18px; letter-spacing: -.02em; }
        .school-pulse span { color: var(--slate-500); font-size: 11px; font-weight: 700; }
        .school-chevron {
            color: #6366f1;
            transition: transform .2s ease;
            justify-self: end;
        }
        .school-card.is-open .school-chevron { transform: rotate(180deg); }
        .school-card-body {
            padding: 0 18px 18px;
            border-top: 1px solid rgba(148, 163, 184, .18);
        }
        .school-card-body .grid { margin-top: 16px; }
        .school-card-body .card {
            box-shadow: none;
            margin-bottom: 12px;
        }
        .school-card-body .card:last-child { margin-bottom: 0; }
        .section-kicker {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6366f1;
            margin: 18px 0 8px;
        }
        .platform-strip { margin-bottom: 18px; }
        @media (max-width: 980px) {
            .top { position: static; }
            .grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .school-card-head { grid-template-columns: 1fr 1fr; }
            .school-chevron { grid-column: 2; }
        }
        @media (max-width: 760px) {
            .bar-row { grid-template-columns: 1fr; }
            .wrap { padding: 16px 14px 48px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body x-data="superAdminConfirm()">
    <div class="wrap">
        <div class="top">
            <div class="brand">AulaSync <span>Founder Center</span></div>
            <nav class="nav">
                <a href="{{ url('/super-admin') }}" class="{{ ($section ?? '') === 'overview' ? 'active' : '' }}">Resumen</a>
                <a href="{{ url('/super-admin/usage') }}" class="{{ ($section ?? '') === 'usage' ? 'active' : '' }}">Uso</a>
                <a href="{{ url('/super-admin/intelligence') }}" class="{{ ($section ?? '') === 'intelligence' ? 'active' : '' }}">IA</a>
                <a href="{{ url('/super-admin/schools') }}" class="{{ ($section ?? '') === 'schools' ? 'active' : '' }}">Colegios</a>
                <a href="{{ url('/super-admin/health') }}" class="{{ ($section ?? '') === 'health' ? 'active' : '' }}">Salud</a>
                <a href="{{ url('/super-admin/insights') }}" class="{{ ($section ?? '') === 'insights' ? 'active' : '' }}">Hallazgos</a>
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
                <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
            </form>
        @endisset
        @if (session('success')) <p class="ok">{!! session('success') !!}</p> @endif
        @if (session('error')) <p class="err">{{ session('error') }}</p> @endif
        @yield('content')
    </div>
    <div class="sa-overlay" x-cloak x-show="open" x-transition @click.self="open = false" role="dialog" aria-modal="true">
        <div class="sa-dialog">
            <h3>Confirmar eliminación</h3>
            <p x-text="message"></p>
            <div class="sa-actions">
                <button type="button" class="btn btn-ghost" @click="open = false">Cancelar</button>
                <button type="button" class="btn btn-danger" @click="confirm()">Eliminar</button>
            </div>
        </div>
    </div>
    <script>
        function superAdminConfirm() {
            return {
                open: false,
                message: '',
                form: null,
                ask(event, message) {
                    event.preventDefault();
                    this.form = event.target;
                    this.message = message;
                    this.open = true;
                },
                confirm() {
                    if (this.form) {
                        this.form.submit();
                    }
                    this.open = false;
                }
            };
        }
    </script>
</body>
</html>
