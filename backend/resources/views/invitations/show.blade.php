<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: Inter, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FBFAF7;
            color: #1E1133;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: rgba(255,255,255,.92);
            border: 1px solid #eddcf7;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(107, 33, 168, .08);
        }
        h1 { font-size: 1.5rem; margin-bottom: .35rem; }
        p { color: #6B4D87; font-size: .95rem; margin-bottom: 1.25rem; }
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
        input[readonly] { background: #f8f5fb; color: #4c1d95; }
        button {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 12px;
            font-weight: 800;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #8b5cf6, #d946ef);
            margin-top: 6px;
        }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:12px; margin-bottom:12px; font-size:.9rem; }
        .badge { display:inline-block; background:#ede9fe; color:#5b21b6; font-size:.75rem; font-weight:800; padding:4px 8px; border-radius:999px; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">{{ $invitation->roleLabel() }}</span>
        <h1>Activa tu cuenta</h1>
        <p>
            Completa tu nombre y contraseña. El correo ya está asignado a esta invitación
            @if($invitation->colegio)
                de <strong>{{ $invitation->colegio->name }}</strong>
            @endif
            y vence el {{ $invitation->expires_at?->format('d/m/Y H:i') }}.
        </p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ url('/accept-invitation') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $invitation->token }}">
            <label>Correo</label>
            <input type="email" value="{{ $invitation->email }}" readonly>
            <label for="name">Nombre completo</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            <button type="submit">Crear cuenta y entrar</button>
        </form>
    </div>
</body>
</html>
