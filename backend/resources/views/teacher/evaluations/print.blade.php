<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $evaluation->title }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #222; margin: 0; background: #fff; }
        .sheet { max-width: 800px; margin: 24px auto; padding: 28px 36px; }
        h1 { font-size: {{ $evaluation->large_print ? '26px' : '22px' }}; margin: 0 0 8px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; font-size: {{ $evaluation->large_print ? '16px' : '13px' }}; margin-bottom: 18px; }
        .line { border-bottom: 1px solid #444; min-height: 22px; }
        .q { margin: 16px 0; page-break-inside: avoid; font-size: {{ $evaluation->large_print ? '16px' : '13px' }}; }
        .opts { margin: 8px 0 0 18px; }
        .write { border-bottom: 1px dotted #666; height: {{ $evaluation->large_print ? '28px' : '22px' }}; margin-top: 8px; }
        .obs { min-height: 70px; border: 1px solid #999; margin-top: 8px; }
        .actions { text-align: right; margin-bottom: 16px; }
        .actions button { padding: 8px 14px; }
        @media print {
            .actions { display: none; }
            .sheet { margin: 0; padding: 12mm; }
            @page { size: {{ data_get($evaluation->physical_format, 'orientation', 'portrait') === 'landscape' ? 'A4 landscape' : 'A4 portrait' }}; margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="actions">
        <button onclick="window.print()">Imprimir / PDF</button>
        <a href="{{ route('teacher.evaluations.index') }}">Volver</a>
    </div>
    <h1>{{ $evaluation->title }}</h1>
    <div class="meta">
        <div>Colegio: {{ $evaluation->teacher?->settings?->nombre_institucion ?? '________________' }}</div>
        <div>Curso: {{ $evaluation->course?->subject_name }} {{ $evaluation->course?->grade }}</div>
        <div>Estudiante: ______________________________</div>
        <div>Fecha: __________</div>
    </div>
    @if($evaluation->instructions)
        <p><strong>Instrucciones:</strong> {{ $evaluation->instructions }}</p>
    @endif
    @foreach($evaluation->questions as $index => $question)
        <div class="q">
            <strong>{{ $index + 1 }}.</strong> {{ $question->text }} ({{ $question->points }} pts)
            @if(in_array($question->type, ['multiple_choice', 'true_false']) && is_array($question->options))
                <div class="opts">
                    @foreach($question->options as $option)
                        <div>☐ {{ $option }}</div>
                    @endforeach
                </div>
            @else
                <div class="write"></div>
                <div class="write"></div>
                <div class="write"></div>
            @endif
        </div>
    @endforeach
    <p><strong>Observaciones del profesor</strong></p>
    <div class="obs"></div>
    @if(data_get($evaluation->physical_format, 'include_qr'))
        <p style="font-size:12px;margin-top:18px;">Versión digital: {{ url('/e/'.$evaluation->public_token) }}</p>
    @endif
</div>
</body>
</html>
