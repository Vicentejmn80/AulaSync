<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Inter', sans-serif; padding: 24px; color: #0f172a; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; }
        th { background: #0f172a; color: #fff; text-transform: uppercase; letter-spacing: .05em; }
        .session-header { background: #f3f4f6; font-weight: 600; }
        .day-label { font-weight: 700; }
    </style>
</head>
<body>
    <header>
        <h1>Planificación Manual · {{ $planning->month }}/{{ $planning->year }}</h1>
        <p>Curso: {{ optional($planning->course)->full_name ?? 'Sin curso' }}</p>
    </header>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Inicio</th>
                <th>Desarrollo</th>
                <th>Cierre</th>
            </tr>
        </thead>
        <tbody>
            @foreach(json_decode($planning->sessions, true) ?? [] as $index => $session)
                <tr>
                    <td class="session-header">{{ $index + 1 }}</td>
                    <td>{{ $session['date'] ?? '—' }}</td>
                    <td>{{ $session['inicio'] ?? '—' }}</td>
                    <td>{{ $session['desarrollo'] ?? '—' }}</td>
                    <td>{{ $session['cierre'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer style="margin-top: 24px; font-size: 11px; color: #475569;">
        Documento generado automáticamente por Nova Academy.
    </footer>
</body>
</html>
