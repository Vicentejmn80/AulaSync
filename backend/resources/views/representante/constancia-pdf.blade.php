<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de estudio</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; padding: 28px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { line-height: 1.6; }
        .box { border: 1px solid #cbd5e1; border-radius: 12px; padding: 18px; margin-top: 18px; }
        .muted { color: #64748b; font-size: 12px; }
        .sign { margin-top: 48px; text-align: center; }
    </style>
</head>
<body>
    <p class="muted">AulaSync · {{ $school }}</p>
    <h1>Constancia de estudio</h1>
    <p>
        Se hace constar que <strong>{{ $student->name }}</strong>
        @if($student->document_id) (cédula {{ $student->document_id }}) @endif
        se encuentra inscrito(a) en <strong>{{ $school }}</strong>
        en el grado <strong>{{ $student->grade ?? '—' }}{{ $student->section ? ' / '.$student->section : '' }}</strong>.
    </p>
    <div class="box">
        <p class="muted">Emitida a solicitud de {{ $parent->name }}.</p>
        <p class="muted">Fecha de emisión: {{ $issued->format('d/m/Y') }}</p>
    </div>
    <div class="sign">
        <p>______________________________</p>
        <p class="muted">Dirección / Coordinación</p>
    </div>
</body>
</html>
