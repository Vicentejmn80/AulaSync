<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unirse como familia · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: Inter, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F6F3FF;
            color: #1C1233;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border: 1px solid #E8E0F5;
            border-radius: 28px;
            padding: 28px 26px;
            box-shadow: 0 24px 60px rgba(107, 33, 168, .10);
        }
        .badge { display:inline-block; background:#ede9fe; color:#5b21b6; font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; padding:5px 10px; border-radius:999px; margin-bottom:12px; }
        h1 { font-size: 1.45rem; margin-bottom: .4rem; letter-spacing: -.03em; }
        p { color: #6B4D87; font-size: .95rem; line-height: 1.5; margin-bottom: 1.1rem; }
        .kids { display:flex; flex-direction:column; gap:8px; margin: 0 0 18px; }
        .kid {
            display:flex; align-items:center; gap:10px;
            border:1px solid #EDE7F6; background:#FBF9FF; border-radius:16px; padding:10px 12px;
        }
        .avatar {
            width:38px; height:38px; border-radius:12px; display:grid; place-items:center;
            color:#fff; font-weight:800; background:linear-gradient(135deg,#7C3AED,#EC4899);
        }
        label { display:block; font-size:.8rem; font-weight:700; margin: 0 0 .35rem; }
        input {
            width: 100%;
            border: 1px solid #e2d4f0;
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            margin-bottom: 12px;
            background: #fff;
        }
        .hint { font-size: .75rem; color: #7c6b8a; margin: -8px 0 12px; }
        .actions { display:flex; gap:8px; margin-top: 6px; }
        button, .ghost {
            flex: 1;
            border: 0;
            border-radius: 12px;
            padding: 12px;
            font-weight: 800;
            font-size: .9rem;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        button { color: #fff; background: linear-gradient(135deg, #8b5cf6, #d946ef); }
        .ghost { color: #6B4D87; background: #f8f5fb; border: 1px solid #eddcf7; display:inline-flex; align-items:center; justify-content:center; }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:12px; margin-bottom:12px; font-size:.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Familia</span>
        <h1>Te invitaron a seguir a tus hijos</h1>
        <p>
            @if($share['school'] ?? null)
                En <strong>{{ $share['school'] }}</strong>.
            @endif
            Crea tu cuenta una vez. Si hay hermanos, entran juntos con este mismo enlace.
        </p>

        @if(!empty($share['students']))
            <div class="kids">
                @foreach($share['students'] as $kid)
                    <div class="kid">
                        <div class="avatar">{{ mb_strtoupper(mb_substr($kid['name'], 0, 1)) }}</div>
                        <div>
                            <strong>{{ $kid['name'] }}</strong>
                            <div style="font-size:12px;color:#7c6b8a">{{ trim(($kid['grade'] ?? '').' '.($kid['section'] ?? '')) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        @if(!empty($blocked))
            <div class="error">Esta invitación es para representantes. Cierra sesión e inténtalo de nuevo.</div>
            <div class="actions"><a class="ghost" href="{{ url('/login') }}">Ir al inicio de sesión</a></div>
        @else
            <form method="POST" action="{{ route('familia.join.store') }}">
                @csrf
                <input type="hidden" name="school" value="{{ $invite->colegio?->invite_code }}">
                <input type="hidden" name="code" value="{{ $invite->invite_code }}">
                <label for="name">Tu nombre</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" placeholder="Ej. María Pérez">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="180" autocomplete="email">
                <p class="hint">Con este correo entrarás después. Si ya tienes cuenta de representante, se sumarán los hijos.</p>
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="hint">Mínimo 8 caracteres.</p>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <div class="actions">
                    <a class="ghost" href="{{ url('/login') }}">Ya tengo cuenta</a>
                    <button type="submit">Crear cuenta</button>
                </div>
            </form>
        @endif
    </div>
</body>
</html>
