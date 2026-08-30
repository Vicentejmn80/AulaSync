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
        button {
            color: #fff;
            background: linear-gradient(135deg, #8b5cf6, #d946ef);
        }
        .ghost {
            color: #6B4D87;
            background: #f8f5fb;
            border: 1px solid #eddcf7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:12px; margin-bottom:12px; font-size:.9rem; }
        .badge { display:inline-block; background:#ede9fe; color:#5b21b6; font-size:.75rem; font-weight:800; padding:4px 8px; border-radius:999px; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="card">
        @php
            $isTeacherCode = isset($teacherInvite) && $teacherInvite;
            $schoolName = $colegio->name ?? $invitation?->colegio?->name;
            $teacherName = $teacherInvite->display_name ?? $invitation?->name;
        @endphp
        <span class="badge">{{ $isTeacherCode ? 'Docente' : $invitation->roleLabel() }}</span>
        <h1>
            @if($isTeacherCode || $invitation?->role === \App\Models\Invitation::ROLE_DOCENTE)
                Activa tu cuenta de profesor
            @else
                Activa tu cuenta
            @endif
        </h1>
        <p>
            Bienvenido a AulaSync. Completa tu registro para comenzar
            @if($schoolName)
                en <strong>{{ $schoolName }}</strong>
            @endif
            .
            @if($invitation?->expires_at)
                El enlace vence el {{ $invitation->expires_at->format('d/m/Y H:i') }}.
            @endif
        </p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        @if ($isTeacherCode)
            <form method="POST" action="{{ route('onboarding.teacher.store') }}">
                @csrf
                <input type="hidden" name="school" value="{{ $colegio->invite_code }}">
                <input type="hidden" name="code" value="{{ $teacherInvite->invite_code }}">
                <label>Nombre</label>
                <input id="name" name="name" value="{{ old('name', $teacherName) }}" required maxlength="255" autocomplete="name" @if($teacherName) readonly @endif>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $teacherInvite->email) }}" required maxlength="180" autocomplete="email">
                <p class="hint">Usa este correo para iniciar sesión después.</p>
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="hint">La contraseña debe tener al menos 8 caracteres.</p>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <div class="actions">
                    <a class="ghost" href="{{ url('/login') }}">Cancelar</a>
                    <button type="submit">Crear cuenta</button>
                </div>
            </form>
        @else
            <form method="POST" action="{{ $invitation->role === \App\Models\Invitation::ROLE_DOCENTE ? route('onboarding.teacher.store') : url('/accept-invitation') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $invitation->token }}">
                <label>Nombre</label>
                <input id="name" name="name" value="{{ old('name', $invitation->name) }}" required maxlength="255" autocomplete="name" @if($invitation->name) readonly @endif>
                <label>Email</label>
                <input type="email" value="{{ $invitation->email }}" readonly>
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="hint">La contraseña debe tener al menos 8 caracteres.</p>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <div class="actions">
                    <a class="ghost" href="{{ url('/login') }}">Cancelar</a>
                    <button type="submit">Activar cuenta</button>
                </div>
            </form>
        @endif
    </div>
</body>
</html>
