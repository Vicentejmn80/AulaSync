<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Formación') — AulaSync</title>
    <style>
        :root { --ink:#1E1133; --muted:#6B4D87; --line:#EDDDF7; --bg:#FBFAF7; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Inter, system-ui, sans-serif; color: var(--ink); background: var(--bg); }
        a { color: inherit; }
        .bar { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:18px 6vw; }
        .brand { font-weight:900; letter-spacing:-.03em; text-decoration:none; }
        .brand span { background:linear-gradient(90deg,#8b5cf6,#d946ef); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .who { color:var(--muted); font-size:.9rem; display:flex; gap:14px; align-items:center; }
        .who form { margin:0; }
        .who button { border:0; background:transparent; color:var(--muted); font-weight:700; cursor:pointer; }
        .wrap { width:min(920px, 92vw); margin:0 auto 72px; }
        .hero { padding:12px 0 28px; }
        .kicker { font-size:.75rem; letter-spacing:.16em; text-transform:uppercase; font-weight:800; color:#a855f7; }
        h1 { font-size:clamp(2rem, 5vw, 3.2rem); line-height:1.05; margin:.35rem 0 .8rem; }
        .lead { font-size:1.05rem; color:var(--muted); max-width:38rem; }
        .meta { display:flex; gap:18px; flex-wrap:wrap; margin-top:18px; color:var(--muted); font-weight:700; }
        .card { background:#fff; border:1px solid var(--line); border-radius:1.4rem; padding:18px 18px; display:grid; grid-template-columns:auto 1fr auto; gap:16px; align-items:center; margin-bottom:12px; text-decoration:none; }
        .card.locked { opacity:.55; }
        .num { font-weight:900; color:#d946ef; width:2rem; }
        .card h2 { margin:0 0 4px; font-size:1.05rem; }
        .card p { margin:0; color:var(--muted); font-size:.9rem; }
        .go { border:0; border-radius:999px; padding:.55rem 1rem; font-weight:800; background:linear-gradient(135deg,#8b5cf6,#d946ef); color:#fff; text-decoration:none; }
        .ghost { color:var(--muted); font-weight:800; font-size:.85rem; }
        .barline { height:8px; background:#F3E8FF; border-radius:999px; overflow:hidden; margin:8px 0; }
        .barline span { display:block; height:100%; background:linear-gradient(90deg,#8b5cf6,#f472b6); }
        .player { background:#111; border-radius:1.4rem; overflow:hidden; aspect-ratio:16/9; margin:18px 0 22px; }
        .player iframe, .player video { width:100%; height:100%; border:0; }
        .back { color:#7c3aed; font-weight:800; text-decoration:none; }
        .actions { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-top:28px; flex-wrap:wrap; }
        button.done { border:0; border-radius:999px; padding:.7rem 1.1rem; font-weight:800; cursor:pointer; background:#1E1133; color:#fff; }
        .empty { padding:48px 0; color:var(--muted); }
        @media (max-width:700px) {
            .card { grid-template-columns:auto 1fr; }
            .card .go, .card .ghost { grid-column:2; justify-self:start; }
        }
    </style>
</head>
<body>
    <header class="bar">
        <a class="brand" href="{{ route('formacion.index') }}">AulaSync <span>Formación</span></a>
        <div class="who">
            <span>{{ $user->name }}</span>
            @if($user->hasSaasAccess())
                <a href="{{ url('/dashboard') }}">Ir a AulaSync</a>
            @endif
            <form method="POST" action="{{ url('/logout') }}">@csrf<button type="submit">Salir</button></form>
        </div>
    </header>
    <main class="wrap">
        @yield('content')
    </main>
</body>
</html>
