<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación no válida · AulaSync</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#FBFAF7; color:#1E1133; padding:24px; }
        .card { max-width:420px; background:#fff; border:1px solid #eddcf7; border-radius:1.5rem; padding:2rem; text-align:center; }
        a { color:#7c3aed; font-weight:800; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Este enlace ya no sirve</h1>
        <p>
            @if($invitation?->role === \App\Models\Invitation::ROLE_DOCENTE)
                La invitación no existe, ya fue usada o venció (valen 7 días). Pide un enlace nuevo a la dirección de tu colegio e inicia sesión después en /login.
            @else
                La invitación no existe, ya fue usada o venció (valen 48 horas). Pide un enlace nuevo a quien te invitó e inicia sesión después en /login.
            @endif
        </p>
        <p><a href="{{ url('/login') }}">Ir a iniciar sesión</a></p>
    </div>
</body>
</html>
