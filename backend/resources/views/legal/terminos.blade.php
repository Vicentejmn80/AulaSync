<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos de Servicio · AULASYNC</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #12143A; --indigo: #2D3494; --border: #E4E3F0; --bg: #FBFAF7; --text: #52577C; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--ink); line-height: 1.7; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 64px 24px 80px; }
        a.back { display: inline-flex; align-items: center; gap: 8px; color: var(--indigo); font-weight: 600; text-decoration: none; margin-bottom: 32px; }
        h1 { font-family: 'Manrope', sans-serif; font-weight: 800; font-size: 2rem; margin-bottom: 8px; }
        .badge { display: inline-block; font-size: 12.5px; font-weight: 700; color: #B4761F; background: #FDF0DD; border-radius: 20px; padding: 5px 14px; margin-bottom: 24px; }
        h2 { font-family: 'Manrope', sans-serif; font-size: 1.15rem; margin: 28px 0 10px; }
        p, li { color: var(--text); font-size: 15px; margin-bottom: 12px; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="{{ route('welcome') }}">&larr; Volver al inicio</a>
        <span class="badge">Documento en preparación</span>
        <h1>Términos de Servicio</h1>
        <p>
            AULASYNC se encuentra en etapa de programa piloto. Estamos formalizando los términos de servicio
            definitivos junto con los primeros colegios aliados. Este documento se actualizará antes del
            lanzamiento comercial.
        </p>

        <h2>Uso de la plataforma</h2>
        <p>
            El acceso a AULASYNC está destinado a colegios, docentes, representantes y estudiantes autorizados
            por una institución educativa participante en el programa piloto.
        </p>

        <h2>Disponibilidad</h2>
        <p>
            Al tratarse de una plataforma en desarrollo activo, algunas funciones pueden cambiar o mejorarse
            durante la etapa piloto. Comunicaremos cambios relevantes a los colegios participantes.
        </p>

        <h2>Contacto</h2>
        <p>
            Para dudas sobre estos términos, solicita una demo desde la página principal e indícalo en el
            formulario; nuestro equipo se pondrá en contacto contigo.
        </p>
    </div>
</body>
</html>
