<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $evaluation->title }}</title>
    <style>
        body { font-family: Inter, 'Segoe UI', system-ui, sans-serif; color: #132036; margin: 0; background: #F3F5F8; }
        .sheet { max-width: 840px; margin: 24px auto; padding: 30px 34px; background: #fff; border-radius: 18px; box-shadow: 0 12px 36px rgba(16, 24, 40, 0.1); }
        .header { display: grid; grid-template-columns: 64px 1fr auto; gap: 12px; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #D6DCE5; padding-bottom: 14px; }
        .logo-box { width: 64px; height: 64px; border-radius: 14px; background: linear-gradient(135deg,#6C63FF,#FF6B9D); display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 42px; height: 42px; object-fit: contain; }
        .inst { font-size: 19px; font-weight: 800; margin: 0; }
        .sub { margin: 3px 0 0; color: #5C6B81; font-size: 12px; }
        .meta-pill { font-size: 11px; font-weight: 700; background: #EEF2FF; color: #4338CA; border-radius: 999px; padding: 6px 10px; }
        h1 { font-size: {{ $evaluation->large_print ? '26px' : '22px' }}; margin: 0 0 8px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; font-size: {{ $evaluation->large_print ? '16px' : '13px' }}; margin-bottom: 18px; }
        .line { border-bottom: 1px solid #5B6577; min-height: 22px; }
        .q { margin: 16px 0; page-break-inside: avoid; font-size: {{ $evaluation->large_print ? '16px' : '13px' }}; }
        .opts { margin: 8px 0 0 18px; }
        .write { border-bottom: 1px dashed #67758B; height: {{ $evaluation->large_print ? '28px' : '22px' }}; margin-top: 8px; }
        .obs { min-height: 70px; border: 1px solid #9AA4B2; margin-top: 8px; border-radius: 8px; }
        .actions { text-align: right; margin-bottom: 16px; }
        .actions button, .actions a { border: 0; border-radius: 999px; padding: 8px 14px; text-decoration: none; font-weight: 700; }
        .actions button { background: #4F46E5; color: #fff; cursor: pointer; }
        .actions a { background: #EEF2FF; color: #4338CA; }
        .rubric { margin-top: 14px; font-size: 12px; color: #475467; }
        @media print {
            .actions { display: none; }
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; max-width: none; box-shadow: none; border-radius: 0; }
            @page { size: {{ data_get($evaluation->physical_format, 'paper_size', 'A4') }} {{ data_get($evaluation->physical_format, 'orientation', 'portrait') }}; margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="actions">
        <button onclick="window.print()">Imprimir / PDF</button>
        <a href="{{ route('teacher.evaluations.index') }}">Volver</a>
    </div>
    <div class="header">
        <div class="logo-box">
            <img src="/images/aulasync-mark.png" alt="Logo AulaSync">
        </div>
        <div>
            <p class="inst">{{ $evaluation->teacher?->settings?->nombre_institucion ?? 'Institución educativa' }}</p>
            <p class="sub">Plantilla profesional de evaluación · AulaSync</p>
        </div>
        <span class="meta-pill">{{ strtoupper($evaluation->mode) }} · {{ strtoupper((string) data_get($evaluation->physical_format, 'paper_size', 'A4')) }}</span>
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
    <div class="rubric">
        Puntaje total: {{ $evaluation->total_points }} pts ·
        Aprobación sugerida: {{ $evaluation->passing_score }} pts
    </div>
    <p><strong>Observaciones del profesor</strong></p>
    <div class="obs"></div>
    @if(data_get($evaluation->physical_format, 'include_qr'))
        <p style="font-size:12px;margin-top:18px;">Versión digital: {{ url('/e/'.$evaluation->public_token) }}</p>
    @endif
</div>
</body>
</html>
