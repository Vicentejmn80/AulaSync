<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Calificaciones</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; }
        .header { border-bottom: 2px solid #0ea5e9; padding-bottom: 12px; margin-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .logo-box {
            width: 54px; height: 54px; border: 1px solid #cbd5e1; border-radius: 8px;
            text-align: center; line-height: 54px; font-size: 9px; color: #64748b;
            font-weight: 700; letter-spacing: .08em;
        }
        .header h1 { font-size: 20px; color: #0f172a; margin: 0 0 4px; }
        .header p { margin: 0; color: #64748b; font-size: 12px; }
        .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; font-weight: 700; }
        .meta-value { font-size: 12px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        .student-info { margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 6px; }
        .student-info table { width: 100%; }
        .student-info td { padding: 2px 8px; font-size: 12px; }
        .student-info .label { color: #64748b; width: 120px; }
        .course-section { margin-bottom: 20px; page-break-inside: avoid; }
        .course-section h3 { font-size: 13px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin: 0 0 8px; }
        .course-section .teacher { font-size: 10px; color: #64748b; margin: -4px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { background: #f1f5f9; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 1px solid #cbd5e1; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        .average-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11px; }
        .average-high { background: #d1fae5; color: #065f46; }
        .average-mid { background: #fef3c7; color: #92400e; }
        .average-low { background: #ffe4e6; color: #9f1239; }
        .global { text-align: right; margin-top: 16px; padding-top: 12px; border-top: 2px solid #0ea5e9; }
        .global .value { font-size: 24px; font-weight: 900; }
        .footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
        .signatures { margin-top: 28px; border-top: 1px solid #cbd5e1; padding-top: 18px; }
        .signatures-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .signatures-table td { border: none; text-align: center; padding: 0 12px; vertical-align: top; }
        .signature-line { height: 42px; border-bottom: 1px solid #64748b; margin: 0 auto; width: 88%; }
        .signature-label { margin-top: 8px; font-size: 11px; font-weight: 700; color: #334155; }
        .signature-hint { margin-top: 2px; font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .07em; }
    </style>
</head>
<body>
    @php
        $settings = auth()->user()->settings;
        $institutionName = $settings?->nombre_institucion ?? 'AulaSync';
        $schoolYear = data_get($settings?->preferencias, 'periodo_academico', now()->year . '-' . now()->copy()->addYear()->year);
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 64px;">
                    <div class="logo-box">LOGO</div>
                </td>
                <td style="padding-left: 12px;">
                    <p style="font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #94a3b8; font-weight: 700; margin-bottom: 4px;">
                        Informe Oficial
                    </p>
                    <h1>{{ $institutionName }}</h1>
                    <p>Boleta de Calificaciones · Año Escolar {{ $schoolYear }}</p>
                </td>
                <td style="width: 140px; text-align: right;">
                    <p class="meta-label">Fecha de emisión</p>
                    <p class="meta-value">{{ now()->format('d/m/Y') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="student-info">
        <table>
            <tr><td class="label">Estudiante:</td><td><strong>{{ $student->name }}</strong></td></tr>
            <tr><td class="label">Grado:</td><td>{{ $student->grade }} {{ $student->section ? '/ ' . $student->section : '' }}</td></tr>
            <tr><td class="label">Asignaturas:</td><td>{{ $courseData->count() }}</td></tr>
        </table>
    </div>

    @foreach($courseData as $course)
        <div class="course-section">
            <h3>{{ $course['course_name'] }}</h3>
            <p class="teacher">Docente: {{ $course['teacher_name'] }}</p>

            <table>
                <thead>
                    <tr>
                        <th>Actividad</th>
                        <th>Tipo</th>
                        <th>Nota</th>
                        <th>Máx</th>
                        <th>%</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($course['activities'] as $act)
                        <tr>
                            <td>{{ $act['title'] }}</td>
                            <td>{{ $act['type'] }}</td>
                            <td><strong>{{ $act['score'] }}</strong></td>
                            <td>{{ $act['max_score'] }}</td>
                            <td>{{ $act['percentage'] }}%</td>
                            <td>{{ $act['due_date'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color: #94a3b8; font-style: italic;">Sin actividades calificadas.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <p style="text-align: right; margin: 8px 0 0; font-size: 12px;">
                Promedio de la asignatura:
                <span class="average-badge {{ $course['promedio'] >= 70 ? 'average-high' : ($course['promedio'] >= 60 ? 'average-mid' : 'average-low') }}">
                    {{ $course['promedio'] }}%
                </span>
            </p>
        </div>
    @endforeach

    <div class="global">
        <p style="margin: 0; font-size: 11px; color: #64748b;">Promedio Global</p>
        <p class="value" style="color: {{ $globalAverage >= 70 ? '#065f46' : ($globalAverage >= 60 ? '#92400e' : '#9f1239') }};">
            {{ round($globalAverage, 1) }}%
        </p>
    </div>

    <div class="signatures">
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <p class="signature-label">Firma del Director(a)</p>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <p class="signature-label">Sello Oficial del Establecimiento</p>
                    <p class="signature-hint">Sello institucional</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Generado el {{ now()->format('d/m/Y H:i') }} · AulaSync Intelligence</p>
    </div>
</body>
</html>
