<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Invitación a AulaSync</title>
</head>
<body style="font-family: Inter, system-ui, sans-serif; color:#1E1133; line-height:1.5; padding:24px;">
    <p>Hola {{ $teacherName }},</p>
    <p>
        Has sido invitado a unirte a <strong>{{ $colegioName }}</strong> como profesor
        @if($assignment)
            de <strong>{{ $assignment }}</strong>
        @endif.
    </p>
    <p>Para activar tu cuenta y comenzar a usar la plataforma, abre este enlace:</p>
    <p><a href="{{ $link }}" style="color:#7c3aed; font-weight:700;">{{ $link }}</a></p>
    @if($code)
        <p>O ingresa este código en AulaSync: <strong>{{ $code }}</strong></p>
    @endif
    <p>Este enlace expira el {{ $expiresAt }}.</p>
    <p>¡Te esperamos!<br>El equipo de AulaSync</p>
</body>
</html>
